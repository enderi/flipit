<?php

namespace App\Dealers\TrickGame;


class FourStreetGameStatus
{
    private $actions;
    private $gameStatus;
    private $cards;
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

    public function __construct($game)
    {
        $this->game = $game;
        $this->currentHand = $game->hand;
        $this->actions = $this->currentHand != null ? $this->currentHand->actions : collect([]);
        $this->joinedPlayers = collect([]);
        $this->options = collect([]);
        $this->seatByPlayerUuid = [];
        $this->newHandRequested = collect([]);
        $this->parseStatus();
    }

    private function parseStatus()
    {
        $this->gameStatus = 'waiting_for_opponent';
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
            if($currKey == 'all_cards_revealed'){
                $this->allCardsRevealed = true;
                continue;
            }
            if($currKey == 'player_joined'){
                $this->joinedPlayers->push($data);
                $this->seatByPlayerUuid[$data['player_uuid']] = $data['seat_number'];
                if($this->joinedPlayers->count() == 2){
                    $this->gameStatus = 'ready_to_start';
                }
                continue;
            }
            if ($currKey == 'pocket_card') {
                $this->cardIndex++;
                $seatNo = $data['seat_number'];
                $cards[$seatNo]->push($data['card']);
            }
            if (in_array($currKey, ['flop_card', 'turn_card', 'river_card'])) {
                $this->cardIndex++;
                $cards['community']->push($data['card']);
            }

            if ($currKey == 'new_street_dealt') {
                $waitingActions = collect($playerUuids);
                $this->gameStatus = $data['value'];
                if($this->gameStatus == 'pocket_cards') {
                    $this->pocketCardsDealt = true;
                } else if($this->gameStatus == 'flop') {
                    $this->flopDealt = true;
                } else if($this->gameStatus == 'turn') {
                    $this->turnDealt = true;
                }else if($this->gameStatus == 'river') {
                    $this->riverDealt = true;
                }
            }
            if ($currKey == 'player_action') {
                if ($data['action'] == 'new_hand'){
                    $this->newHandRequested->push($data['player_uuid']);
                    $this->newHandRequested = $this->newHandRequested->unique();
                }
                $waitingActions = $waitingActions->filter(function ($a) use ($data) {
                    return $a != $data['player_uuid'];
                });
            }
        }
        $this->cards = $cards;
        $options = [];
        if (!$waitingActions->isEmpty()) {
            foreach ($waitingActions as $a) {
                $seat = $this->seatByPlayerUuid[$a];
                if (!array_key_exists($seat, $options)) {
                    $options[$seat] = collect([]);
                }
                if($this->handEnded()){
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
        } else {
        }
        $this->options = collect($options);

    }

    public function getCardIndex() {
        return $this->cardIndex;
    }

    public function isWaitingForUserActions() {
        return $this->options->count() > 0;
    }

    public function isPocketCardsDealt() {
        return $this->pocketCardsDealt;
    }

    public function readyToDealPocketCards() {
        return $this->joinedPlayers->count() == 2 && !$this->pocketCardsDealt;
    }

    public function readyToDealFlop() {
        return $this->pocketCardsDealt && !$this->flopDealt;
    }

    public function readyToDealTurn() {
        return $this->flopDealt && !$this->turnDealt;
    }

    public function readyToDealRiver() {
        return $this->turnDealt && !$this->riverDealt;
    }

    public function readyForNewHand() {
        return $this->newHandRequested->count() == 2;
    }

    public function getCards($seatNo) {
        if($this->allCardsRevealed) {
            return $this->cards;
        } else {
            return $this->hiddenCards($seatNo);
        }
    }

    private function hiddenCards($seatNo){
        $new = array();

        foreach ($this->cards as $k => $v) {
            if($k == $seatNo || $k == 'community') {
                $new[$k] = clone $v;
            }else {
                $new[$k] = collect($v)->map(function($c){ return '??';});
            }
        }
        return $new;
    }

    public function getOptions() {
        return $this->options;
    }

    public function getSeatForPlayerUuid($uuid){
        if(array_key_exists($uuid, $this->seatByPlayerUuid)) {
            return $this->seatByPlayerUuid[$uuid];
        }else {
            return null;
        }
    }

    public function areAllCardsRevealed() {
        return $this->allCardsRevealed;
    }

    public function getGameStatus() {
        return $this->gameStatus;
    }

    public function handEnded() {
        return $this->riverDealt;
    }

    public function toString() {
        return nl2br('STATUS: ' . $this->gameStatus
        . ', PlayerCount: ' . $this->joinedPlayers->count()
        . ', Waiting for user: ' . ($this->isWaitingForUserActions() ? 'yes' : 'no')
        . ', options: ' . json_encode($this->options)
        . "\n"
    );
    }
}
