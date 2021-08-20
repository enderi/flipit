<?php

namespace App\Dealers\FourStreetGames;

use App\Lib\DeckLib\Deck;
use App\Lib\DeckLib\evaluate;
use App\Models\Hand;

class FourStreetGameStatus
{
    public static $gameStates = [
        'WAITING_PLAYERS' => 'WAITING_PLAYERS',
        'PLAYERS_JOINED' => 'PLAYERS_JOINED',
        'PREFLOP' => 'PREFLOP',
        'FLOP_DEALT' => 'FLOP_DEALT',
        'TURN_DEALT' => 'TURN_DEALT',
        'RIVER_DEALT' => 'RIVER_DEALT',
        'HAND_ENDED' => 'HAND_ENDED',
    ];

    private $streetsDealt = [
        'pocket_cards' => false,
        'flop' => false,
        'turn' => false,
        'river' => false
    ];

    private $actions;
    private $gameStatus;
    private $cards;
    private $cardsInDealOrder;
    private $options;
    private $joinedPlayers;
    private $seatByPlayerUuid;
    private $cardIndex = 0;
    private $allCardsRevealed = false;
    private $newHandRequested;
    private $cardsInSeatRevealed;
    private $resultSaved = false;
    private Deck $deck;

    public function __construct($game)
    {
        $this->setData($game->players, $game->hand);
    }

    public function setData($players, Hand $currentHand)
    {
        $this->joinedPlayers = $players;
        $this->currentHand = $currentHand;
        $this->deck = $currentHand->getDeck();
        $this->actions = $currentHand->actions;
        $this->initializeState();
    }

    private function initializeState()
    {
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
        $this->gameStatus = FourStreetGameStatus::$gameStates['WAITING_PLAYERS'];
        if ($this->joinedPlayers->count() == 2) {
            $this->gameStatus = FourStreetGameStatus::$gameStates['PLAYERS_JOINED'];
        } else {
            return;
        }
        $cardsInDealOrder = collect([]);
        $cards = [];
        $playerUuids = collect([]);
        foreach ($this->joinedPlayers as $p) {
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
                $this->deck->draw(1);
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
                $this->deck->draw(1);
                $cards['community']->push($data['card']);
                $cardsInDealOrder->push([
                    'target' => 'community',
                    'card' => $data['card'],
                    'index' => $this->cardIndex
                ]);
            }

            if ($currKey == 'new_street_dealt') {
                $this->gameStatus = $data['value'];
                $this->streetsDealt[$data['value']] = true;
                if (in_array($data['value'], ['pocket_cards', 'flop', 'turn'])) {
                    $waitingActions = collect($playerUuids);
                }
            }
            if ($currKey == 'hand_ended') {
                $waitingActions = collect($playerUuids);
                $this->gameStatus = 'hand_ended';
                $this->resultSaved = true;
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
                if ($this->waitingForNewHandRequest()) {
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
        if ($this->joinedPlayers->count() != 2) {
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
    }

    public function isFlopDealt()
    {
        return $this->streetsDealt['flop'];
    }

    public function readyToDealPocketCards()
    {
        return !$this->streetsDealt['pocket_cards'];
    }

    public function readyToDealFlop()
    {
        return $this->streetsDealt['pocket_cards'] && !$this->streetsDealt['flop'];
    }

    public function readyToDealTurn()
    {
        return $this->streetsDealt['flop'] && !$this->streetsDealt['turn'];
    }

    public function readyToDealRiver()
    {
        return $this->streetsDealt['turn'] && !$this->streetsDealt['river'];
    }

    public function handEnded()
    {
        return $this->streetsDealt['river'] && !$this->resultSaved;
    }

    public function waitingForNewHandRequest()
    {
        return $this->streetsDealt['river'] && $this->resultSaved && $this->newHandRequested->count() < 2;
    }

    public function readyForNewHand()
    {
        return $this->resultSaved && $this->newHandRequested->count() == 2;
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

    public function getAllCards() {
        return $this->cards;
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

    public function getDeck() {
        return $this->deck;
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
