<?php

namespace App\Dealers\PokerGames\FourStreetGames;

use App\Dealers\DealerBase;
use App\Dealers\DealerUtils\ActionHandler;
use App\Dealers\PokerGames\PokerEvaluator;
use App\DomainObjects\Card;
use App\DomainObjects\Deck;
use App\Models\Hand;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

abstract class HoldemBaseDealer extends DealerBase
{
    const PLAYER_ACTION = 'player_action';
    const PLAYER_OPTION = 'player_option';
    const KEY = 'key';
    const PREFLOP = 'pocket_cards';

    private ?FourStreetGameStatus $status = null;

    private ?PokerEvaluator $pokerEvaluator = null;

    private ActionHandler $blockingActions;
    private ActionHandler $nonBlockingActions;
    private $shouldBroadcast = false;

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

    public function addUserOption($optionKey, $playerUuid)
    {
        $this->createAction([
            self::KEY => self::PLAYER_OPTION,
            'player_uuid' => $playerUuid,
            'option' => $optionKey
        ]);
    }

    public function tick(string $playerUuid, $forceBroadcast = false): array
    {
        $reqPlayerCount = 2;
        if($this->game->players->count() == $reqPlayerCount){
            if($this->game->hand == null || $this->isNewHandRequested()) {
                $this->createNewHand();
                $this->shouldBroadcast = true;
            }
        } else {
            return [
                'handStatus' => 'WAITING_PLAYERS'
            ];
        }
        $modifiedState = true;
        $counter = 0;
        while($modifiedState && $counter < 10) {
            $modifiedState =  $this->refreshState();
            $counter++;
        }

        if($this->shouldBroadcast || $forceBroadcast){
            $this->broadcastStatus();
        }

        return $this->getStatus($playerUuid);
    }

    protected function refreshState():bool
    {
        $this->currentHand->refresh();
        $this->status = new FourStreetGameStatus($this->currentHand->getDeck());

        $this->blockingActions = new ActionHandler([1,2]);
        $this->nonBlockingActions = new ActionHandler([1,2]);
        foreach($this->currentHand->actions as $action){
            $data = $action['data'];
            $currKey = $data['key'];
            if ($currKey == 'pocket_card') {
                $this->status->dealCard($data['seat_number']);
            }
            if (in_array($currKey, ['flop_card', 'turn_card', 'river_card'])) {
                $this->status->dealCard('community');
            }
            if ($currKey == 'new_street_dealt') {
                $this->status->setStatus($data['value']);
                if($data['value'] == 'pocket_cards') {
                    $this->nonBlockingActions->addOptionForAll('show_cards');
                }
                if(in_array($data['value'], ['pocket_cards', 'flop', 'turn'])) {
                    $this->blockingActions->addOptionForAll('confirm');
                } else {
                    $this->nonBlockingActions->addOptionForAll('new_hand');
                }
            }
            if($currKey == 'player_action') {
                $pUuid = $data['player_uuid'];
                $player = $this->findPlayerByUuid($pUuid);
                $this->blockingActions->playerActed($player['seat_number'], $data['action']);
            }
            if($currKey == 'player_option') {
                $pUuid = $data['player_uuid'];
                $player = $this->findPlayerByUuid($pUuid);
                $key = $data['option'];
                $this->nonBlockingActions->playerActed($player['seat_number'], $data['option']);
                if($key == 'show_cards') {
                    $this->status->playerCardRevealed($player->seat_number);
                }
            }
        }

        if($this->blockingActions->isBlocked()){
            $this->shouldBroadcast = false;
            return false;
        }

        $stateModified = $this->proceedIfPossible();
        if($stateModified) {
            $this->shouldBroadcast = true;
        }
        return $stateModified;
    }

    private function isNewHandRequested() : bool {
        $requests = $this->currentHand->actions->filter(function($d){
            return $d['data']['key'] == 'player_option' && $d['data']['option'] == 'new_hand';
        })->map(function($item) {
            $pUuid = $item['data']['player_uuid'];
            return $this->findPlayerByUuid($pUuid)->seat_number;
        });
        $result = $requests->contains(1) && $requests->contains(2);
        return $result;
    }

    private function proceedIfPossible(): bool
    {
        $actions = null;
        if ($this->status->readyToDealPocketCards()) {
            $actions = $this->dealPocketCards();
        } else if ($this->status->readyToDealFlop()) {
            $actions = $this->dealFlop();
        } else if ($this->status->readyToDealTurn()) {
            $actions = $this->dealTurn();
        } else if ($this->status->readyToDealRiver()) {
            $actions = $this->dealRiver();
            $actions = $actions->merge($this->saveResult());
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

    protected function getStatus($playerUuid): array
    {
        if ($this->status == null || $this->status->getGameStatus() == 'WAITING_PLAYERS') {
            return [
                'handStatus' => 'WAITING_PLAYERS'
            ];
        }
        $myPlayer = $this->findPlayerByUuid($playerUuid);
        $mySeat = $myPlayer->seat_number;
        $opponentSeat = $mySeat == 1 ? 2 : 1;

        $result = [
            'mySeat' => $mySeat,
            'actions' => $this->blockingActions->getOptions(),
            'options' => $this->nonBlockingActions->getOptions(),
            'myPlayerUuid' => $playerUuid,
            'handStatus' => $this->status->getGameStatus(),
            'cardsInDealOrder' => $this->status->getCardsInDealOrder($mySeat),
            'result' => $this->game->hand->result,
            'handValues' => []
        ];

        if($this->status->isFlopDealt()) {
            $values = $this->getHandValuesForSeats();
            if (!$this->status->isCardsInSeatRevealed($opponentSeat)) {
                $values[$opponentSeat] = [];
            }
            $result['handValues'] = $values;
        }
        if($this->status->areAllCardsRevealed() && $this->status->isFlopDealt()){
            $deck = $this->status->getDeck();
            $result['odds'] = $this->getOddsUntilRiver($this->status->getBinaryCards(), $deck);
        }

        return $result;
    }

    private function getHandValuesForSeats(): array
    {
        $cards = $this->status->getBinaryCards();
        if(!array_key_exists('community', $cards)) {
            return [];
        }
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
        $this->shouldBroadcast = true;
    }

    private function dealPocketCards()
    {
        $dealtCards = collect([]);
        for ($cc = 0; $cc < $this->getCardCount(); $cc++) {
            for ($i = 0; $i < $this->game->players->count(); $i++) {
                $result = $this->status->dealCard($this->game->players[$i]->seat_number);
                $action = [
                    self::KEY => 'pocket_card',
                    'card' => $result,
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
        $card_index = $deck->getCardCount();
        for ($i = 0; $i < $numberOfCards; $i++) {
            $actions->push([
                    self::KEY => $key,
                    'community' => true,
                    'card_index' => $card_index++,
                    'card' => $deck->drawOne()->toString()
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

    /**
     * @param $pUuid
     * @return mixed
     */
    protected function findPlayerByUuid($pUuid)
    {
        $player = $this->game->players->firstWhere('uuid', $pUuid);
        return $player;
    }
}
