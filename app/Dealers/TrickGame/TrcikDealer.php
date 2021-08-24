<?php

namespace App\Dealers\TrickGame;

use App\Dealers\DealerBase;
use App\Lib\DeckLib\Deck;
use App\Models\GamePlayerMapping;
use App\Models\Hand;
use App\Models\Player;
use Ramsey\Uuid\Uuid;

abstract class TrcikDealer extends DealerBase {
    const PLAYER_ACTION = 'player_action';

    const KEY = 'key';

    const PREFLOP = 'pocket_cards';
    private $status;

    public function initWithGame($game)
    {
        $this->game = $game;
        $this->currentHand = $game->getCurrentHand();
        $this->refreshState();
    }

    protected function refreshState() {
        $finished = false;
        $counter = 0;
        $modified = false;
        while(!$finished && $counter < 10) {
            $status = new FourStreetGameStatus($this->game);
            $this->status = $status;
            if(!$status->isWaitingForUserActions()) {
                $this->proceedIfPossible($status);
                $modified = true;
            } else {
                $finished = true;
            }
            $counter++;
        }
        if($modified) {
            $this->broadcastStatus();
        }
    }

    public abstract function getGameType(): String;
    public abstract function getCardCount();

    public function joinAsPlayer()
    {
        $player = Player::create([
            'uuid' => Uuid::uuid4(),
            'game_id' => $this->game['id'],
            'seat_number' => $this->game->players->count() + 1,
            'ready' => 1
        ]);

        $mapping = GamePlayerMapping::create(
            [
                'uuid' => Uuid::uuid4(),
                'game_id' => $this->game->id,
                'player_id' => $player->id
            ]);
        return $mapping;
    }

    public function addUserAction($actionKey, $playerUuid)
    {
        $this->createAction([
            self::KEY => self::PLAYER_ACTION,
            'player_uuid' => $playerUuid,
            'action' => $actionKey
        ]);
        $this->refreshState();
    }

    public function tick($playerUuid, $forceBroadcast = false): array
    {
        $this->refreshState();
        return $this->getStatus($playerUuid);
    }

    protected function getStatus($playerUuid): array
    {

        if($this->status->getGameStatus() == 'waiting_for_opponent') {
            return [
                'handStatus' => $this->status->getGameStatus()
            ];
        }
        $mySeat = $this->status->getSeatForPlayerUuid($playerUuid);
        $opponentSeat = $mySeat == 1? 2: 1;
        $cards = $this->status->getCards($this->status->getSeatForPlayerUuid($playerUuid));
        $values = $this->getHandValuesForSeats();
        $myHandValue = $values[$mySeat];
        if($this->status->areAllCardsRevealed()){
            $opponentHandValue = $values[$opponentSeat];
        } else {
            $opponentHandValue = [];
        }



        $result = [
            'cards' => $cards,
            'mySeat' => $mySeat,
            'options' => $this->status->getOptions(),
            'myPlayerUuid' => $playerUuid,
            'myHandValue' => $myHandValue,
            'opponentHandValue' => $opponentHandValue,
            'handStatus' => $this->status->getGameStatus()
        ];

        if($this->status->handEnded()) {
            $result['result'] = $this->currentHand->result;
        }
        return $result;
    }

    private function getHandValuesForSeats() {
        $cards1 = $this->status->getCards('1');
        $cards2 = $this->status->getCards('2');
        $seat1 = $this->getHandValues($cards1[1], $cards1['community']);
        $seat2 = $this->getHandValues($cards2[2], $cards2['community']);
        return [
            1 => $seat1,
            2 => $seat2
        ];
    }


    private function createNewHand()
    {
        Hand::where('game_id', $this->game->id)
            ->update(['ended' => true]);
        $deck = new Deck();
        $deck->initialize();
        $deck->shuffle();

        $this->currentHand = Hand::create([
            'game_id' => $this->game->id,
            'data' => [],
            'uuid' => Uuid::uuid4(),
            'ended' => false,
            'deck' => $deck->toString()
        ]);
        foreach($this->game->players as $player) {
            $this->createAction([
                'player_uuid' => $player->uuid,
                'seat_number' => $player->seat_number,
                self::KEY => 'player_joined'
            ]);
        }

    }

    protected abstract function getHandValues($cs, $communityCardsItems);
    protected abstract function getBestHand($handCards, $communityCards): array;

    public function proceedIfPossible(FourStreetGameStatus $status) : bool
    {
        if ($this->currentHand == null) {
            if ($this->game->players->count() == 2) {
                $this->createNewHand();
                return true;
            } else {
                return false;
            }
        }
        $actions = null;
        if($status->readyToDealPocketCards()) {
            $actions = $this->dealPocketCards();
        } else if($status->readyToDealFlop()) {
            $actions = $this->dealFlop();
        } else if($status->readyToDealTurn()) {
            $actions = $this->dealTurn();
        } else if($status->readyToDealRiver()) {
            $actions = $this->dealRiver();
        } else if($status->readyForNewHand()) {
            $this->createNewHand();
        }
        if($actions != null){
            $actions->each(function($action){
                $this->createAction($action);
            });
            return true;
        }
        if($status->handEnded()) {
            /*
            $handValuesBySeat = $this->getHandValuesForSeats();;
            $result = [
                1 => ['hand'=>$handValuesBySeat[1]],
                2 => ['hand'=>$handValuesBySeat[1]],
            ];
            if($handValuesBySeat[1]['value'] == $handValuesBySeat[2]['value']){
                $result[1]['result'] = 'split';
                $result[2]['result'] = 'split';
            }
            if($handValuesBySeat[1]['value'] < $handValuesBySeat[2]['value']){
                $result[1]['result'] = 'win';
                $result[2]['result'] = 'lose';
            }
            if($handValuesBySeat[1]['value'] > $handValuesBySeat[2]['value']){
                $result[1]['result'] = 'lose';
                $result[2]['result'] = 'win';
            }

            $this->currentHand->result = $result;
            $this->currentHand->save();*/
        }
        return false;
    }

    private function dealPocketCards()
    {
        $dealtCards = collect([]);
        $card_index = $this->status->getCardIndex();
        for ($cc = 0; $cc < $this->getCardCount(); $cc++) {
            for ($i = 0; $i < $this->game->players->count(); $i++) {
                $action = [
                        self::KEY => 'pocket_card',
                        'card_index' => $card_index++,
                        'card' => $this->currentHand->getDeck()->getIndex($card_index)->toString(),
                        'player_uuid' => $this->game->players[$i]->uuid,
                        'seat_number' => $this->game->players[$i]->seat_number
                    ];
                $dealtCards->push($action);
            }
        }

        $streetDealtAction = [
            self::KEY => 'new_street_dealt',
            'value' => self::PREFLOP
        ];
        $dealtCards->push($streetDealtAction);
        return $dealtCards;
    }

    private function dealFlop()
    {
        $actions = collect([]);
        $actions->push([
            'key' => 'all_cards_revealed'
        ]);
        return $actions->merge($this->dealCommunityCards('flop', 3));
    }

    private function dealTurn()
    {
        return $this->dealCommunityCards('turn', 1);
    }

    private function dealRiver()
    {
        return $this->dealCommunityCards('river', 1);
    }

    private function dealCommunityCards($streetName, $numberOfCards)
    {
        $actions = collect([]);
        $key = $streetName . '_card';
        $card_index = $this->status->getCardIndex();
        for ($i = 0; $i < $numberOfCards; $i++) {
            $actions->push([
                    self::KEY => $key,
                    'community' => true,
                    'card_index' => $card_index++,
                    'card' => $this->currentHand->getDeck()->getIndex($card_index)->toString()]
                );
        }
        $actions->push([
            self::KEY => 'new_street_dealt',
            'value' => $streetName
        ]);
        return $actions;

    }

}
