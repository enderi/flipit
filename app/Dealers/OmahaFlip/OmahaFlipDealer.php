<?php

namespace App\Dealers\OmahaFlip;

use App\Dealers\FourStreetGames\HoldemBaseDealer;
use App\Dealers\TexasFlip\Combinations;
use App\Lib\DeckLib\card;
use App\Lib\DeckLib\Deck;
use App\Lib\DeckLib\PokerHandEvaluator;
use App\Services\Lookup;
use App\Services\Pokerank;
use App\Services\PokerEvaluator;

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

    public function getGameType(): string
    {
        return self::OMAHA_FLIP;
    }

    public function getCardCount()
    {
        return self::POCKET_CARD_COUNT;
    }

    protected function getHandValues($handCards, $communityCardsItems)
    {
        if (sizeof($communityCardsItems) < 3) {
            return [];
        }

        $communityCards = collect($communityCardsItems)->map(function ($c) {
            return $c;
        });

        return $this->getBestHand($handCards, $communityCards);
    }

    protected function getBestHand($handCards, $communityCards): array
    {
        $evl = new PokerEvaluator();
        
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
                $score = $evl->getValueOfFive($cards[0]->getBinaryValue(), $cards[1]->getBinaryValue(), $cards[2]->getBinaryValue(), $cards[3]->getBinaryValue(), $cards[4]->getBinaryValue());
                if ($bestHand == null || $bestHand['value'] > $score) { 
                    $bestHand = [
                        'value' => $score,
                        'cards' => $hand,
                        'hand'=> $cards 
                    ];
                }
            }
        }
        $bestHand['info'] = $evl->calculateHandName($bestHand['value'], $bestHand['hand']);
        return $bestHand;
    }

    protected function getOddsUntilRiver($handCards, Deck $deck)
    {
        $pokerEvaluator = new PokerEvaluator();
        $cardsInDeck = $deck->getCardIntValues();
        $cardsLeft = 5 - count($handCards['community']);
        $winsBySeat = [
            1 => 0,
            2 => 0,
            'tie' => 0,
            'total' => 0
        ];
        $counter = 0;
        $cardCollections = [];
        foreach ($handCards as $key => $c) {
            $cardCollections[$key] = $this->mapToInts($c);
        }
        foreach (new Combinations($cardsInDeck, $cardsLeft) as $c) {
            $table = array_merge($cardCollections['community'], $c);
            $bestHand1 = 0;
            $bestHand2 = 0;
            foreach (new Combinations($table, 3) as $tableCombination) {
                foreach (new Combinations($cardCollections[1], 2) as $handCards) {
                    $result = $pokerEvaluator->getValueOfFive($tableCombination[0], $tableCombination[1], $tableCombination[2], $handCards[0], $handCards[1]);
                
                    if ($bestHand1 == 0 || $bestHand1 > $result) {
                        $bestHand1 = $result;
                    }
                }
                foreach (new Combinations($cardCollections[2], 2) as $handCards) {
                    $result = $pokerEvaluator->getValueOfFive($tableCombination[0], $tableCombination[1], $tableCombination[2], $handCards[0], $handCards[1]);
                    if ($bestHand2 == 0 || $bestHand2 > $result) {
                        $bestHand2 = $result;
                    }
                }
            }
            if ($bestHand1 < $bestHand2) {
                $winsBySeat[1]++;
            } else if ($bestHand1 > $bestHand2) {
                $winsBySeat[2]++;
            } else {
                $winsBySeat['tie']++;
            }

            $counter++;
        }

        $winsBySeat['total'] = $counter;
        return $winsBySeat;
    }

    /**
     * @param $hand
     * @param Pokerank $pokerank
     * @return \Illuminate\Support\Collection
     */
    protected function mapToInts($hand): array
    {
        $mapped = collect($hand)->map(function ($card) {
            $c = Card::of($card);
            return $c->getBinaryValue();
        });
        return $mapped->toArray();
    }
}
