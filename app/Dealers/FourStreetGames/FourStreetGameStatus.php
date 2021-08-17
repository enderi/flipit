<?php

namespace App\Dealers\FourStreetGames;

use DASPRiD\Enum\Exception\IllegalArgumentException;

class FourStreetGameStatus
{
    private $actions;
    private $gameStatus;
    private $cards;
    private $cardsInDealOrder;
    private $options;
    private $joinedPlayers;
    private $pocketCardsDealt = false;
    private $flopDealt = false;
    private $turnDealt = false;
    private $riverDealt = false;
    private $seatByPlayerUuid;
    private $cardIndex = 0;
    private $allCardsRevealed = false;
    private $newHandRequested;
    private $cardsInSeatRevealed;

    public function __construct($game)
    {
        $this->game = $game;
        $this->currentHand = $game->getCurrentHand();
        $this->actions = $this->currentHand != null ? $this->currentHand->actions : collect([]);
        $this->joinedPlayers = $this->game->players;
        $this->options = collect([]);
        $this->seatByPlayerUuid = [];
        foreach ($this->joinedPlayers as $pl) {
            $this->seatByPlayerUuid[$pl['uuid']] = $pl->seat_number;
        }
        $this->newHandRequested = collect([]);
        $this->cardsInSeatRevealed = [
            1 => false,
            2 => false
        ];
        $this->parseStatus();
    }

    private function parseStatus()
    {
        $this->gameStatus = 'waiting_for_opponent';
        if ($this->joinedPlayers->count() == 2) {
            $this->gameStatus = 'ready_to_start';
        } else {
            return;
        }
        $cardsInDealOrder = collect([]);
        $cards = [];
        $playerUuids = collect([]);
        foreach ($this->game->players as $p) {
            $cards[$p['seat_number']] = collect([]);
            $playerUuids->push($p['uuid']);

        }
        $cards['community'] = collect([]);

        $waitingActions = collect([]);

        foreach ($this->actions as $a) {
            $data = $a->data;
            $currKey = $data['key'];
            if ($currKey == 'all_cards_revealed') {
                $this->allCardsRevealed = true;
                $this->cardsInSeatRevealed[1] = true;
                $this->cardsInSeatRevealed[2] = true;
                continue;
            }
            if ($currKey == 'pocket_card') {
                $this->cardIndex++;
                $seatNo = $data['seat_number'];
                $cards[$seatNo]->push($data['card']);

                $cardsInDealOrder->push([
                    'target' => $seatNo,
                    'card' => $data['card'],
                    'index' => $this->cardIndex
                ]);
            }
            if (in_array($currKey, ['flop_card', 'turn_card', 'river_card'])) {
                $this->cardIndex++;
                $cards['community']->push($data['card']);
                $cardsInDealOrder->push([
                    'target' => 'community',
                    'card' => $data['card'],
                    'index' => $this->cardIndex
                ]);
            }

            if ($currKey == 'new_street_dealt') {
                $waitingActions = collect($playerUuids);
                $this->gameStatus = $data['value'];
                if ($this->gameStatus == 'pocket_cards') {
                    $this->pocketCardsDealt = true;
                } else if ($this->gameStatus == 'flop') {
                    $this->flopDealt = true;
                } else if ($this->gameStatus == 'turn') {
                    $this->turnDealt = true;
                } else if ($this->gameStatus == 'river') {
                    $this->riverDealt = true;
                }
            }
            if ($currKey == 'player_action') {
                if ($data['action'] == 'new_hand') {
                    $this->newHandRequested->push($data['player_uuid']);
                    $this->newHandRequested = $this->newHandRequested->unique();
                }
                if ($data['action'] == 'show_cards') {
                    $seat = $this->seatByPlayerUuid[$data['player_uuid']];
                    $this->cardsInSeatRevealed[$seat] = true;
                    if ($this->cardsInSeatRevealed[1] && $this->cardsInSeatRevealed[2]) {
                        $this->allCardsRevealed = true;
                    }
                    continue;
                }
                $waitingActions = $waitingActions->filter(function ($a) use ($data) {
                    return $a != $data['player_uuid'];
                });
            }
        }
        $this->cards = $cards;
        $this->cardsInDealOrder = $cardsInDealOrder;
        $options = [
            1 => collect([]),
            2 => collect([])
        ];
        if (!$this->allCardsRevealed && sizeof($playerUuids) == 2) {
            foreach ($playerUuids as $plUuid) {
                $seat = $this->seatByPlayerUuid[$plUuid];
                if (!$this->cardsInSeatRevealed[$seat]) {
                    $options[$seat]->push([
                        'text' => 'Show cards',
                        'key' => 'show_cards',
                        'non_blocking' => true
                    ]);
                }
            }
        }
        if (!$waitingActions->isEmpty()) {
            foreach ($waitingActions as $a) {
                $seat = $this->seatByPlayerUuid[$a];
                if ($this->handEnded()) {
                    $options[$seat]->push([
                        'text' => 'New hand',
                        'key' => 'new_hand',
                    ]);
                } else {
                    $options[$seat]->push([
                        'text' => 'Go ahead',
                        'key' => 'confirm',
                    ]);
                }
            }
        }
        $this->options = collect($options);

    }

    public function getCardIndex()
    {
        return $this->cardIndex;
    }

    public function isWaitingForUserActions()
    {
        if($this->joinedPlayers->count() != 2) {
            return false;
        }
        $blockersFound = false;
        if ($this->options[1]->filter(function ($op) {
                return !array_key_exists('non_blocking', $op) || $op['non_blocking'] == false;
            })->count() > 0 ||
            $this->options[2]->filter(function ($op) {
                return !array_key_exists('non_blocking', $op) || $op['non_blocking'] == false;
            })->count() > 0
        ) {
            $blockersFound = true;
        }
        return $blockersFound;

        /*return $this
                ->options
                ->where(function ($op) {
                    return !array_key_exists('non_blocking', $op) || $op['non_blocking'] == false;
                })
                ->count() > 0;
   */
    }

    public function isPocketCardsDealt()
    {
        return $this->pocketCardsDealt;
    }

    public function readyToDealPocketCards()
    {
        return $this->joinedPlayers->count() == 2 && !$this->pocketCardsDealt;
    }

    public function readyToDealFlop()
    {
        return $this->pocketCardsDealt && !$this->flopDealt;
    }

    public function readyToDealTurn()
    {
        return $this->flopDealt && !$this->turnDealt;
    }

    public function readyToDealRiver()
    {
        return $this->turnDealt && !$this->riverDealt;
    }

    public function readyForNewHand()
    {
        return $this->newHandRequested->count() == 2;
    }

    public function getCardsInDealOrder($seat)
    {
        if ($this->allCardsRevealed) {
            return $this->cardsInDealOrder;
        } else {
            return $this->cardsInDealOrder->map(function ($c) use ($seat) {
                if (array_key_exists('target', $c) && ($c['target'] == 'community' || $c['target'] == $seat)) {
                    return $c;
                }
                $c['card'] = '??';
                return $c;
            });
        }
    }

    public function getCards($seatNo)
    {
        if ($this->allCardsRevealed) {
            return $this->cards;
        } else {
            return $this->hiddenCards($seatNo);
        }
    }

    private function hiddenCards($seatNo)
    {
        $new = array();

        foreach ($this->cards as $k => $v) {
            if ($k == $seatNo || $k == 'community') {
                $new[$k] = clone $v;
            } else {
                $new[$k] = collect($v)->map(function ($c) {
                    return '??';
                });
            }
        }
        return $new;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getSeatForPlayerUuid($uuid)
    {
        if (array_key_exists($uuid, $this->seatByPlayerUuid)) {
            return $this->seatByPlayerUuid[$uuid];
        } else {
            return null;
        }
    }

    public function areAllCardsRevealed()
    {
        return $this->allCardsRevealed;
    }

    public function getGameStatus()
    {
        return $this->gameStatus;
    }

    public function isFlopDealt()
    {
        return $this->flopDealt;
    }

    public function handEnded()
    {
        return $this->riverDealt;
    }

    public function toString()
    {
        return nl2br('STATUS: ' . $this->gameStatus
            . ', PlayerCount: ' . $this->joinedPlayers->count()
            . ', Waiting for user: ' . ($this->isWaitingForUserActions() ? 'yes' : 'no')
            . ', options: ' . json_encode($this->options)
            . "\n"
        );
    }
}
