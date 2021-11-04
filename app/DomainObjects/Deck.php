<?php

namespace App\DomainObjects;

use http\Exception\InvalidArgumentException;

class Deck
{
    private $cards = array();
    private $cursor;

    public function __construct()
    {
    }

    private function setCards($cards) {
        $this->cards = $cards;
        $this->cursor = 0;
    }

    public function getCards() {
        return $this->cards;
    }

    public function getCardIntValues() {
        return collect($this->cards)->map(function($c) {
            return $c->getBinaryValue(); })->toArray();
    }

    public function initialize() {
        $cardCollection = new CardCollection();
        $suits = Card::getSuits();
        $ranks = Card::getRanks();

        for ($i = 0; $i < count($suits); $i++)
        {
            for ($k = 0; $k < count($ranks); $k++)
            {
                $card = new Card($ranks[$k], $suits[$i]);
                $this->cards[]=$card;
                $cardCollection->addCard($card);
            }
        }
        $this->cursor = 0;
    }

    public static function of($deckString) {
        $cards = [];
        for($i=0; $i<52; $i++) {
            $rank = substr($deckString, $i*2, 1);
            $suit = substr($deckString, $i*2+1, 1);
            $card = new Card($rank, $suit);
            $cards[]=$card;
        }
        $deck = new Deck();
        $deck->setCards($cards);
        return $deck;
    }

    public function shuffle()
    {
        if (count($this->cards))
        {
            shuffle($this->cards);
        }
        else
        {
            return false;
        }
    }

    public function drawOne() {
        if($this->cursor == 52) {
            throw new InvalidArgumentException("No more cards");
        }
        $card = $this->cards[$this->cursor];
        $this->cursor++;
        return $card;
    }

    public function drawMany($count=1)
    {
        if($count == 0 || $this->cursor + $count > 52) {
            throw new InvalidArgumentException("Stupid argument");
        }
        if ($count == 1)
        {
            $this->cursor++;
            $cards = array_splice($this->cards, $this->cursor, $count);
            return $cards;
        }
        else
        {
            $cards = array();

            for ($i = 0; $i < $count; $i++)
            {
                $card = $this->cards[0];
                array_splice($this->cards, 0, 1);
                $cards[]=$card;
            }

            return $cards;
        }
    }

    public function card($rank, $suit)
    {
        $card = false;

        for ($i = 0; $i < count($this->cards); $i++)
        {
            if ($this->cards[$i]->getRank() == $rank && $this->cards[$i]->getSuit() == $suit)
            {
                $card = $this->cards[$i];
                array_splice($this->cards, $i, 1);
                break;
            }
        }

        return $card;
    }

    public function getIndex($index) {
        return $this->cards[$index];
    }

    public function getCardCount() {
        return $this->cursor;
    }

    public function contains($card)
    {
        return in_array($card, $this->cards);
    }

    public function toString() {
        $deckString = '';
        for ($i = 0; $i < count($this->cards); $i++)
        {
            $deckString = $deckString . ($this->cards[$i]->toString());
        }
        return $deckString;
    }
}
