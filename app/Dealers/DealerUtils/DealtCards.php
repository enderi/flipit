<?php

namespace App\Dealers\DealerUtils;

class DealtCards {
    private $cards = [];
    private $cardsInDealOrder = [];
    private $cardCount = 0;

    public function addCard($target, $card) {
        if(!array_key_exists($target, $this->cards)){
            $this->cards[$target] = [];
        }
        array_push($this->cards[$target], $card);
        $this->cardCount++;
        array_push($this->cardsInDealOrder, [
            'target' => $target,
            'card' => $card,
            'index' => $this->cardCount
        ]);
    }

    public function getCardCount() {
        return $this->cardCount;
    }

    public function getDealtCards() {
        return $this->cards;
    }

    public function getCardsInDealOrder() {
        return $this->cardsInDealOrder;
    }
    public function getAllCardsByTarget() {
        return $this->cards;
    }
}
