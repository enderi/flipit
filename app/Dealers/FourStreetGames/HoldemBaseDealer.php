<?php

namespace App\Dealers\FourStreetGames;

use App\Dealers\DealerBase;
use App\Lib\DeckLib\card;
use App\Lib\DeckLib\Deck;
use App\Models\Action;
use App\Models\GamePlayerMapping;
use App\Models\Hand;
use App\Models\Player;
use App\Services\HoldemSolver;
use Ramsey\Uuid\Uuid;

abstract class HoldemBaseDealer extends DealerBase
{
    const PLAYER_ACTION = 'player_action';
    const KEY = 'key';
    const PREFLOP = 'pocket_cards';

    private $status;

    public abstract function getCardCount();

    protected abstract function getHandValues($cs, $communityCardsItems);

    protected abstract function getBestHand($handCards, $communityCards): array;

    public function joinAsPlayer(): GamePlayerMapping
    {
        $player = Player::create([
            'uuid' => Uuid::uuid4(),
            'game_id' => $this->game['id'],
            'seat_number' => $this->game->players->count() + 1,
            'ready' => 1
        ]);
        return GamePlayerMapping::create(
            [
                'uuid' => Uuid::uuid4(),
                'game_id' => $this->game->id,
                'player_id' => $player->id
            ]);
    }

    public function addUserAction($actionKey, $playerUuid)
    {
        $this->createAction([
            self::KEY => self::PLAYER_ACTION,
            'player_uuid' => $playerUuid,
            'action' => $actionKey
        ]);
        $this->refreshState(true);
    }

    public function tick($playerUuid): array
    {
        $this->refreshState();
        return $this->getStatus($playerUuid);
    }

    private function proceedIfPossible(FourStreetGameStatus $status): bool
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
        if ($status->readyToDealPocketCards()) {
            $actions = $this->dealPocketCards();
        } else if ($status->readyToDealFlop()) {
            $actions = $this->dealFlop();
        } else if ($status->readyToDealTurn()) {
            $actions = $this->dealTurn();
        } else if ($status->readyToDealRiver()) {
            $actions = $this->dealRiver();
        } else if ($status->readyForNewHand()) {
            $this->createNewHand();
        }
        if ($actions != null) {
            $actions->each(function ($action) {
                $this->createAction($action);
            });

            return true;
        }
        if ($status->handEnded()) {
        }
        return false;
    }

    protected function refreshState($forceBroadcast = false)
    {
        $counter = 0;
        $finished = false;
        $modified = false;
        while (!$finished && $counter < 10) {
            $status = new FourStreetGameStatus($this->game);
            $this->status = $status;
            if (!$status->isWaitingForUserActions()) {
                $modified = $this->proceedIfPossible($status);
                $finished = false;
            } else {
                $finished = true;
            }
            $counter++;
        }
        if ($modified || $forceBroadcast) {
            $this->broadcastStatus();
        }
    }

    protected function getStatus($playerUuid): array
    {

        if ($this->status->getGameStatus() == 'waiting_for_opponent') {
            return [
                'handStatus' => $this->status->getGameStatus()
            ];
        }
        $mySeat = $this->status->getSeatForPlayerUuid($playerUuid);
        $opponentSeat = $mySeat == 1 ? 2 : 1;
        $cards = $this->status->getCards($mySeat);
        $values = $this->getHandValuesForSeats();
        $myHandValue = $values[$mySeat];
        if ($this->status->areAllCardsRevealed()) {
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
            'handStatus' => $this->status->getGameStatus(),
            'cardsInDealOrder' => $this->status->getCardsInDealOrder($mySeat),
            'allCardsRevealed' => $this->status->areAllCardsRevealed()
        ];

        if ($this->status->handEnded()) {
            $result['result'] = $this->currentHand->result;
        }

        /*if($this->status->isFlopDealt()) {
            $cardIndex = $this->status->getCardIndex();
            $deck = $this->currentHand->getDeck();
            $remaining = $deck->getRemainingFromIndex($cardIndex);
            $solver = new HoldemSolver();
            $communityCards = $cards['community']->map(function($c) { return card::of($c); });
            $hand1 = $cards['1']->map(function($c) { return card::of($c); });
            $hand2 = $cards['2']->map(function($c) { return card::of($c); });

            $result['odds'] = $solver->calc($remaining, $communityCards, $hand1, $hand2);

        }*/
        return $result;
    }

    private function getHandValuesForSeats()
    {
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
        foreach ($this->game->players as $player) {
            $this->createAction([
                'player_uuid' => $player->uuid,
                'seat_number' => $player->seat_number,
                self::KEY => 'player_joined'
            ]);
        }

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
        return $this->dealCommunityCards('flop', 3);
    }

    private function dealTurn()
    {
        return $this->dealCommunityCards('turn', 1);
    }

    private function dealRiver()
    {
        $actions = collect([]);
        $actions->push([
            'key' => 'all_cards_revealed'
        ]);
        return $actions->merge($this->dealCommunityCards('river', 1));
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
