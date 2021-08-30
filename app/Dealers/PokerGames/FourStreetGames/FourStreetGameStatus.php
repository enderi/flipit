<?php

namespace App\Dealers\PokerGames\FourStreetGames;

use App\Dealers\DealerUtils\DealtCards;
use App\DomainObjects\Card;
use App\DomainObjects\Deck;
use App\Models\Hand;
use Arrayzy\ArrayImitator as A;


class FourStreetGameStatus
{
    private $gameStatus;
    private $options;
    private $allCardsRevealed = false;

    private DealtCards $dealtCards;

    private Deck $deck;
    private array $seatSeesCards = [];

    public function __construct(Deck $deck)
    {
        $this->deck = $deck;
        $this->dealtCards = new DealtCards();
        $this->gameStatus = 'READY';
    }

    public function readyForNewHand()
    {
        return $this->gameStatus == 'READY';
    }

    public function playerCardRevealed($seatNumber)
    {
        $this->seatSeesCards[$seatNumber] = true;
    }

    private function initializeState()
    {
        $this->options = collect([]);
        $this->newHandRequested = collect([]);
        $this->cardsInSeatRevealed = [
            1 => false,
            2 => false
        ];
        $this->huStatus = new FourStreetHeadUpGame();
    }

    public function dealCard($target) {
        $card = $this->deck->drawOne();
        $this->dealtCards->addCard($target, $card);
        return $card->toString();
    }

    public function getDealtCards() {
        return $this->dealtCards;
    }

    public function setStatus($status) {
        $this->gameStatus = $status;
    }



    public function readyToDealPocketCards()
    {
        return $this->gameStatus == 'READY';
    }

    public function readyToDealFlop()
    {
        return $this->gameStatus == 'pocket_cards';
    }

    public function readyToDealTurn()
    {
        return $this->gameStatus == 'flop';
    }

    public function readyToDealRiver()
    {
        return $this->gameStatus == 'turn';
    }

    public function handEnded()
    {
        return $this->gameStatus == 'river';
    }

    public function getCardsInDealOrder($seat)
    {
        $seatSeesCardsArr = A::create($this->seatSeesCards);
        $cardsToReturn = [];
        $index = 0;
        foreach($this->dealtCards->getCardsInDealOrder() as $c) {
            $t = $c['target'];
            $card = [
                'target' => $c['target'],
                'card_index' => $index++
            ];
            if($t == 'community' || $seat == $t || ($seatSeesCardsArr->containsKey($seat) && $seatSeesCardsArr[$seat] == true )){
                $card['card'] = $c['card']->toString();
            } else {
                $card['card'] = '??';
            }
            $cardsToReturn[] = $card;
        }

        return $cardsToReturn;
    }

    public function getBinaryCards()
    {
        $vals = $this->dealtCards->getAllCardsByTarget();
        $new = [];
        foreach ($vals as $k => $v) {
            $new[$k] = collect($v)->map(function ($card) {
                return $card->getBinaryValue();
            })->toArray();
        }
        return $new;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function seatSeesAllCards($seatNumber) : bool
    {
        if(array_key_exists($seatNumber, $this->seatSeesCards)){
            return $this->seatSeesCards[$seatNumber];
        }
        return false;
    }

    public function getGameStatus()
    {
        return $this->gameStatus;
    }

    public function getDeck()
    {
        return $this->deck;
    }

    public function isFlopDealt() {
        return in_array($this->gameStatus, ['flop', 'turn', 'river']);
    }
}
