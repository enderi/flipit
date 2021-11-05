<?php

namespace App\Dealers\PokerGames\FourStreetGames;

use App\Dealers\BaseDealer;
use App\Dealers\Exceptions\NewHandRequestedException;

abstract class HoldemDealer extends BaseDealer
{
    protected abstract function getOddsSolver() : HoldemOddsSolver;
    protected abstract function getHandCardCount(): int;

    const PREFLOP = "preflop";
    const FLOP = 'flop';
    const TURN = 'turn';
    const RIVER = 'river';

    const CARD_INDEX = 'cardIndex';
    const NEW_HAND = 'new_hand';

    const OPTION = 'option';
    const COMMUNITY = 'community';
    const HAND_ENDED = 'HAND_ENDED';
    const SHOWDOWN = 'showdown';
    const SHOW_CARDS = 'show_cards';

    const NEW_STREET_DEALT = 'new_street_dealt';
    const VALUE = 'value';
    const CARD = 'card';
    const TARGET = 'target';
    const CONFIRM = "confirm";
    const ACTION = 'action';
    const PLAYER_UUID = 'player_uuid';
    const COMMUNITY_CARD = 'community_card';
    const WAITING_FOR_CONFIRMATIONS = 'waiting_for_confirmations';

    const DEALER_MAY_PROCEED = "DEALER_MAY_PROCEED";

    private string $gamePhase = self::PREFLOP;
    private array $revealCardsForSeat = [];

    public function refreshState()
    {
        if(!$this->blockingActions->isBlocked()) {
            switch ($this->gamePhase) {
                case self::PREFLOP:
                    $this->dealPocketCards($this->getHandCardCount());
                    break;
                case self::FLOP:
                    $this->dealCommunityCards(self::FLOP, 3);
                    break;
                case self::TURN:
                    $this->dealCommunityCards(self::TURN, 1);
                    break;
                case self::RIVER:
                    $this->dealCommunityCards(self::RIVER, 1);
                    break;
                case self::SHOWDOWN:
                    throw new NewHandRequestedException();
            }
        }
    }

    protected function processAction($data)
    {
        $key = $data['key'];
        if($key == self::NEW_STREET_DEALT) {
            $actions = $data['data'];
            $street = $data['value'];
            foreach ($actions as $action) {
                $cardIndex = $action[self::CARD_INDEX];
                $target = $action[self::TARGET];
                $this->dealCard($cardIndex, $target);
            }
            if($street == self::PREFLOP) {
                $this->nonBlockingActions->addOptionForAll(self::SHOW_CARDS);
            }
            if(in_array($street, ['preflop', 'flop', 'turn'])) {
                $this->state = self::WAITING_FOR_CONFIRMATIONS;
                $this->addBlockingAction(self::CONFIRM);
            } else {
                $this->gamePhase = self::SHOWDOWN;
                $this->state = self::HAND_ENDED;
                $this->blockingActions->addOptionForAll(self::NEW_HAND);
            }
        }

        if($key == self::ACTION) {
            $action = $data['action'];
            $seat = $this->getSeatForPlayerUuid($data[self::PLAYER_UUID]);
            $this->blockingActions->playerActed($seat, $action);
            if(!$this->blockingActions->isBlocked()){
                switch ($this->gamePhase) {
                    case self::PREFLOP:
                        $this->gamePhase = self::FLOP;
                        $this->state = self::DEALER_MAY_PROCEED;
                        break;
                    case self::FLOP:
                        $this->gamePhase = self::TURN;
                        $this->state = self::DEALER_MAY_PROCEED;
                        break;
                    case self::TURN:
                        $this->gamePhase = self::RIVER;
                        $this->state = self::HAND_ENDED;
                        break;
                }
            }
        }

        if($key == self::OPTION){
            $option = $data['option'];
            $seat = $this->getSeatForPlayerUuid($data[self::PLAYER_UUID]);
            if($option == self::SHOW_CARDS) {
                $this->revealCardsForSeat[] = $seat;
            }
            $this->nonBlockingActions->playerActed($seat, $option);
        }
    }

    private function dealPocketCards(int $cardCount)
    {
        $action = [
            'key' => self::NEW_STREET_DEALT,
            'value' => self::PREFLOP,
            'data' => []
        ];
        $seats = $this->getSeatNumbers();
        for ($cc = 0; $cc < $cardCount; $cc++) {
            for ($i = 0; $i < $this->getPlayerCount(); $i++) {
                $target = $seats[$i];
                $action['data'][] = [
                    self::KEY => self::POCKET_CARD,
                    self::CARD_INDEX => $this->dealtCards,
                    self::TARGET => $target
                ];
                $this->dealtCards++;
            }
        }
        $this->addNewAction($action);
    }

    private function dealCommunityCards(string $streetName, int $cardCount)
    {
        $action = [
            'key' => self::NEW_STREET_DEALT,
            'value' => $streetName,
            'data' => []
        ];
        for ($i = 0; $i < $cardCount; $i++) {
            $action['data'][] = [
                self::KEY => self::COMMUNITY_CARD,
                self::CARD_INDEX => $this->dealtCards,
                self::TARGET => self::COMMUNITY
            ];
            $this->dealtCards++;
        }
        $this->addNewAction($action);
        $this->state = $streetName;
    }

    public function getStatus($playerUuid): array
    {
        $mySeat = $this->table->getSeatForUuid($playerUuid);
        $base = parent::getStatus($playerUuid);

        $base['handValues'] = $this->getOddsSolver()->getHandValues($base['cardsInDealOrder']);
        if(!in_array($mySeat, $this->revealCardsForSeat) && $this->gamePhase != self::SHOWDOWN) {
            foreach(array_keys($base['handValues']) as $key) {
                if($key != $mySeat){
                    unset($base['handValues'][$key]);
                }
            }
            $newCards = [];
            foreach($base['cardsInDealOrder'] as $item) {
                if($item['target'] != self::COMMUNITY && $item['target'] != $mySeat) {
                    $item['card'] = '??';
                }
                $newCards[] = $item;
            }
            $base['cardsInDealOrder'] = $newCards;
        } else {
            try {
                $base['odds'] = $this->getOddsSolver()->evaluate($base['cardsInDealOrder'], $this->deck);
            }catch (\Exception $e) {
                // just let it go
            }
        }
        return $base;
    }

    private function addBlockingAction(string $key)
    {
        $this->blockingActions->addOptionForAll($key);

    }
}
