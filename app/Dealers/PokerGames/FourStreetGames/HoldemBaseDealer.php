<?php

namespace App\Dealers\PokerGames\FourStreetGames;

use App\Dealers\DealerBase;
use App\Dealers\PokerGames\PokerEvaluator;
use App\DomainObjects\Card;
use App\DomainObjects\Deck;
use App\Models\Hand;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

abstract class HoldemBaseDealer extends DealerBase
{
    const PLAYER_ACTION = 'player_action';
    const KEY = 'key';
    const PREFLOP = 'pocket_cards';

    private ?FourStreetGameStatus $status = null;

    private ?PokerEvaluator $pokerEvaluator = null;

    public abstract function getCardCount();

    protected abstract function getHandValues($cs, $communityCardsItems);

    protected abstract function getOddsUntilRiver($handCards, Deck $deck);

    public function addUserAction($actionKey, $playerUuid)
    {
        $this->createAction([
            self::KEY => self::PLAYER_ACTION,
            'player_uuid' => $playerUuid,
            'action' => $actionKey
        ]);
    }

    public function tick(string $playerUuid, $forceBroadcast = false): array
    {
        $shouldBroadCast = false;
        $reqPlayerCount = 2;
        if($this->game->players->count() == $reqPlayerCount){
            if($this->game->hand == null) {
                $this->createNewHand();
            }
            $shouldBroadCast = $this->refreshState();
        }
        if($shouldBroadCast || $forceBroadcast){
            $this->broadcastStatus();
        }

        return $this->getStatus($playerUuid);
    }

    private function proceedIfPossible(): bool
    {
        if ($this->status->readyForNewHand()) {
            $this->createNewHand();
            return true;
        }

        if($this->status->isWaitingForUserActions()){
            return false;
        }

        if ($this->game->hand == null) {
            return false;
        }
        $actions = null;
        if ($this->status->readyToDealPocketCards()) {
            $actions = $this->dealPocketCards();
        } else if ($this->status->readyToDealFlop()) {
            $actions = $this->dealFlop();
        } else if ($this->status->readyToDealTurn()) {
            $actions = $this->dealTurn();
        } else if ($this->status->readyToDealRiver()) {
            $actions = $this->dealRiver();
        } else if($this->status->handEnded()){
            $actions = $this->saveResult();
        }
        if ($actions != null) {
            $actions->each(function ($action) {
                $this->createAction($action);
            });
            return true;
        }
        return false;
    }

    private function saveResult(): Collection {
        $result = $this->getHandValuesForSeats();
        $resultToSave = [
            1 => 'tie',
            2 => 'tie'
        ];
        $h1 = $result[1];
        $h2 = $result[2];
        if($h1->value < $h2->value) {
            $resultToSave[1] = 'win';
            $resultToSave[2] = 'lose';
        }
        if($h1->value > $h2->value) {
            $resultToSave[1] = 'lose';
            $resultToSave[2] = 'win';
        }
        $action = [
            self::KEY => 'hand_ended',
            'results' => $resultToSave
        ];
        $resultToSave['hands'] = $result;
        $this->currentHand->refresh();
        $this->currentHand['result'] = $resultToSave;
        $this->currentHand->save();

        return collect([$action]);
    }

    protected function refreshState():bool
    {
        $this->status = new FourStreetGameStatus($this->game);
        $modified = false;
        $counter = 0;
        while($this->proceedIfPossible() && $counter <10){
            $this->game->refresh();
            $this->status = new FourStreetGameStatus($this->game);
            $modified = true;
            $counter++;
        }
        return $modified;
    }

    protected function getStatus($playerUuid): array
    {
        if ($this->status == null || $this->status->getGameStatus() == 'WAITING_PLAYERS') {
            return [
                'handStatus' => 'WAITING_PLAYERS'
            ];
        }
        $mySeat = $this->status->getSeatForPlayerUuid($playerUuid);
        $opponentSeat = $mySeat == 1 ? 2 : 1;
        $values = $this->getHandValuesForSeats();
        $myHandValue = $values[$mySeat];
        if ($this->status->areAllCardsRevealed()) {
            $opponentHandValue = $values[$opponentSeat];
        } else {
            $opponentHandValue = [];
        }

        $result = [
            'gameId' => $this->game->id,
            'mySeat' => $mySeat,
            'options' => $this->status->getOptions(),
            'myPlayerUuid' => $playerUuid,
            'myHandValue' => $myHandValue,
            'opponentHandValue' => $opponentHandValue,
            'handStatus' => $this->status->getGameStatus(),
            'cardsInDealOrder' => $this->status->getCardsInDealOrder($mySeat),
            'allCardsRevealed' => $this->status->areAllCardsRevealed(),
        ];

        if($this->status->areAllCardsRevealed() && $this->status->isFlopDealt()){
            $deck = $this->status->getDeck();
            $result['odds'] = $this->getOddsUntilRiver($this->status->getBinaryCards($mySeat), $deck);
        }

        return $result;
    }

    private function getHandValuesForSeats(): array
    {
        $cards = $this->status->getBinaryCards();
        $seat1 = $this->getHandValues($cards[1], $cards['community']);
        $seat2 = $this->getHandValues($cards[2], $cards['community']);
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
        $hand = Hand::create([
            'game_id' => $this->game->id,
            'data' => [],
            'uuid' => Uuid::uuid4(),
            'ended' => false,
            'deck' => $deck
        ]);
        $this->game->hand_id = $hand->id;
        $this->game->save();
        $this->game->refresh();
        $this->currentHand = $this->game->hand;
    }

    private function dealPocketCards()
    {
        $deck = $this->status->getDeck();
        $dealtCards = collect([]);
        $card_index = $this->status->getCardIndex();

        for ($cc = 0; $cc < $this->getCardCount(); $cc++) {
            for ($i = 0; $i < $this->game->players->count(); $i++) {
                $action = [
                    self::KEY => 'pocket_card',
                    'card_index' => $card_index++,
                    'card' => $deck->draw(1)->toString(),
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

    private function dealCommunityCards(string $streetName, int $numberOfCards)
    {
        $actions = collect([]);
        $deck = $this->status->getDeck();
        $key = $streetName . '_card';
        $card_index = $this->status->getCardIndex();
        for ($i = 0; $i < $numberOfCards; $i++) {
            $actions->push([
                    self::KEY => $key,
                    'community' => true,
                    'card_index' => $card_index++,
                    'card' => $deck->draw(1)->toString()
            ]);
        }
        $actions->push([
            self::KEY => 'new_street_dealt',
            'value' => $streetName
        ]);
        return $actions;

    }

    protected function mapToInts($hand): array
    {
        $mapped = collect($hand)->map(function ($card) {
            $c = Card::of($card);
            return $c->getBinaryValue();
        });
        return $mapped->toArray();
    }

    protected function getEvaluator() : PokerEvaluator{
        if($this->pokerEvaluator == null){
            $this->pokerEvaluator = new PokerEvaluator();
        }
        return $this->pokerEvaluator;
    }
}
