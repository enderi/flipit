<?php

namespace App\Dealers\PokerGames\OmahaFlip;

use App\Dealers\PokerGames\PokerEvaluator;
use App\Dealers\PokerGames\FourStreetGames\HoldemOddsSolver;
use App\DomainObjects\Card;
use App\DomainObjects\Combinations;
use App\DomainObjects\Deck;

class OmahaHoldemOddsSolver extends HoldemOddsSolver
{
    public function evaluate($dealtCards, Deck $deck) {
        $handCards = $this->getHandCards($dealtCards);
        if(!key_exists('community', $handCards)) {
            return null;
        }
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
            $cardCollections[$key] = $c;
        }
        foreach (new Combinations($cardsInDeck, $cardsLeft) as $c) {
            $table = array_merge($cardCollections['community'], $c);
            $bestHand1 = 0;
            $bestHand2 = 0;
            foreach (new Combinations($table, 3) as $tableCombination) {
                foreach (new Combinations($cardCollections[1], 2) as $handCards) {
                    $result = $this->pokerEvaluator->getValueOfFive($tableCombination[0], $tableCombination[1], $tableCombination[2], $handCards[0], $handCards[1]);

                    if ($bestHand1 == 0 || $bestHand1 > $result) {
                        $bestHand1 = $result;
                    }
                }
                foreach (new Combinations($cardCollections[2], 2) as $handCards) {
                    $result = $this->pokerEvaluator->getValueOfFive($tableCombination[0], $tableCombination[1], $tableCombination[2], $handCards[0], $handCards[1]);
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

    public function getHandValues($dealtCards)
    {
        $cards = $this->getHandCards($dealtCards);
        if(!array_key_exists('community', $cards)) {
            return [];
        }
        $seat1 = $this->getBestHand($cards[1], $cards['community']);
        $seat2 = $this->getBestHand($cards[2], $cards['community']);
        return [
            1 => $seat1,
            2 => $seat2
        ];
    }

    private function getBestHand($dealtCards, $communityCards)
    {
        $handCombinations = [];
        foreach (new Combinations($dealtCards, 2) as $c) {
            $handCombinations[] = $c;
        }

        $tableCombinations = [];
        foreach (new Combinations($communityCards, 3) as $c) {
            $tableCombinations[] = $c;
        }
        $bestHand = null;
        $cardsForBestHand = null;
        for ($i = 0; $i < sizeof($handCombinations); $i++) {
            for ($j = 0; $j < sizeof($tableCombinations); $j++) {
                $hand = array_merge($handCombinations[$i], $tableCombinations[$j]);
                $cards = [];
                foreach ($hand as $cardForThis) {
                    $cards[] = $cardForThis;
                }
                $score = $this->pokerEvaluator->getValueOfFive($cards[0], $cards[1], $cards[2], $cards[3], $cards[4]);
                if ($bestHand == null || $bestHand > $score) {
                    $bestHand =  $score;
                    $cardsForBestHand = $cards;
                }
            }
        }
        return $this->pokerEvaluator->getHandNameForBinaries($cardsForBestHand);
    }
}
