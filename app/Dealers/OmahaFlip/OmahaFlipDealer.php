<?php

namespace App\Dealers\OmahaFlip;

use App\Dealers\FourStreetGames\HoldemBaseDealer;
use App\Dealers\TexasFlip\Combinations;
use App\Lib\DeckLib\card;
use App\Lib\DeckLib\evaluate;

class OmahaFlipDealer extends HoldemBaseDealer
{
    const OMAHA_FLIP = 'OMAHA-FLIP';
    const POCKET_CARD_COUNT = 4;

    public static function of($game): OmahaFlipDealer
    {
        $result = new OmahaFlipDealer();
        $result->initWithGame($game);
        return $result;
    }

    public function getGameType() {
        return self::OMAHA_FLIP;
    }

    public function getCardCount() {
        return self::POCKET_CARD_COUNT;
    }

    protected function getHandValues($cs, $communityCardsItems)
    {
        if (sizeof($communityCardsItems) < 3) {
            return [];
        }
        $handCombinations = [];

        $handCards = collect($cs)->map(function($cardItem) {
            if(is_array($cardItem)) {
                return $cardItem['card'];
            }else{
                dd($cardItem);
            }

        });
        $communityCards = collect($communityCardsItems)->map(function($c){
            return $c['card'];
        });

        return $this->getBestHand($handCards, $communityCards);
    }

    /**
     * @param \Illuminate\Support\Collection $handCards
     * @param array $handCombinations
     * @param \Illuminate\Support\Collection $communityCards
     * @param evaluate $evaluator
     * @return array
     */
    protected function getBestHand($handCards, $communityCards): array
    {
        $evaluator = new evaluate();
        $handCombinations = [];
        $tableCombinations = [];
        foreach (new Combinations($handCards->toArray(), 2) as $c) {
            $handCombinations[] = $c;
        }

        $tableCombinations = [];
        foreach (new Combinations($communityCards->toArray(), 3) as $c) {
            $tableCombinations[] = $c;
        }

        $bestHand = null;
        for ($i = 0; $i < sizeof($handCombinations); $i++) {
            for ($j = 0; $j < sizeof($tableCombinations); $j++) {
                $hand = array_merge($handCombinations[$i], $tableCombinations[$j]);
                $cards = [];
                foreach ($hand as $cardForThis) {
                    $cards[] = card::of($cardForThis);
                }
                $value = $evaluator->getValue($cards);
                $name = $evaluator->getHandName();
                if ($bestHand == null || $bestHand['value'] > $value) {
                    $bestHand = [
                        'value' => $value,
                        'cards' => $hand,
                        'name' => $name
                    ];
                }
            }
        }

        return $bestHand;
    }
}
