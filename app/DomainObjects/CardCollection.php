<?php

namespace App\DomainObjects;

class CardCollection
{

    private $cards;

    public function __construct() {
        $this->cards = [];
    }

    public function addCards($cards) {
        $this->cards = $cards;
    }

    public function addCard($card) {
        $this->cards[] = $card;
    }
}
