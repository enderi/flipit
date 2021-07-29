<?php

namespace App\Dealers\TexasFlip;

use App\Dealers\FourStreetGames\HoldemBaseDealer;
use App\Lib\DeckLib\card;
use App\Lib\DeckLib\evaluate;

class TexasFlipDealer extends HoldemBaseDealer
{
    const TEXAS_FLIP = 'TEXAS-FLIP';
    const POCKET_CARD_COUNT = 2;

    public function getGameType() {
        return self::TEXAS_FLIP;
    }

    public function getCardCount() {
        return self::POCKET_CARD_COUNT;
    }

    public static function of($game): TexasFlipDealer
    {
        $result = new TexasFlipDealer();
        $result->initWithGame($game);
        return $result;
    }

    protected function getHandValues($cs, $communityCardsItems)
    {
        $handCards = $cs->map(function($c){
            return $c['card'];
        });
        $commCards = $communityCardsItems->map(function($c){
            return $c['card'];
        });
        return $this->getBestHand($handCards, $commCards);
    }

    protected function getBestHand($handCards, $communityCards): array
    {
        $evaluator = new evaluate();
        $playerCardsInUse = [];
        foreach($communityCards as $c) {
            $playerCardsInUse[] = $c;
        }
        foreach($handCards as $c) {
            $playerCardsInUse[] = $c;
        }
        if(sizeof($playerCardsInUse) < 5){
            return [];
        }
        $bestHand = null;
        foreach(new Combinations($playerCardsInUse, min(sizeof($playerCardsInUse), 5)) as $c) {
            $cards = [];
            foreach($c as $cardForThis) {
                $cards[] = card::of($cardForThis);
            }
            $value = $evaluator->getValue($cards);
            $name = $evaluator->getHandName();
            if($bestHand == null || $bestHand['value'] > $value) {
                $bestHand = [
                    'value' => $value,
                    'cards' => $c,
                    'name' => $name
                ];
            }
        }
        return $bestHand;
    }
}
