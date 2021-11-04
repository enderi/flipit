<?php

namespace App\Dealers\PokerGames\FourStreetGames;

use App\Dealers\PokerGames\PokerEvaluator;
use App\DomainObjects\Card;
use App\DomainObjects\Deck;

abstract class HoldemOddsSolver
{

    protected PokerEvaluator $pokerEvaluator;

    public function __construct() {
        $this->pokerEvaluator = new PokerEvaluator();
    }

    public abstract function evaluate($handCards, Deck $deck);

    public abstract function getHandValues($dealtCards);

    /**
     * @param $dealtCards
     * @return array
     */
    protected function getHandCards($dealtCards): array
    {
        $handCards = [];
        for($i=0; $i<count($dealtCards); $i++) {
            $dealt = $dealtCards[$i];
            $target = $dealt['target'];
            if (!key_exists($target, $handCards)) {
                $handCards[$target] = [];
            }
            array_push($handCards[$target], Card::of($dealt['card'])->getBinaryValue());
        }
        return $handCards;
    }
}
