<?php

namespace App\Dealers\PokerGames\TexasFlip;

use App\Dealers\PokerGames\FourStreetGames\HoldemOddsSolver;
use App\Dealers\PokerGames\PokerEvaluator;
use App\DomainObjects\Combinations;
use App\DomainObjects\Deck;

class TexasHoldemOddsSolver extends HoldemOddsSolver
{
    public function evaluate($dealtCards, Deck $deck) {
        $cardsInDeck = $deck->getCardIntValues();
        $handCards = $this->getHandCards($dealtCards);
        if(!key_exists('community', $handCards)) {
            return null;
        }
        $cardsLeft = 5 - count($handCards['community']);

        $winsBySeat = [
            1=>0,
            2=>0,
            'tie'=>0,
            'total'=>0
        ];
        $counter = 0;

        $cardCollections = [];
        foreach ($handCards as $key => $c) {
            $cardCollections[$key] = $c;
        }

        foreach(new Combinations($cardsInDeck, $cardsLeft) as $c) {
            $table = array_merge($cardCollections['community'], $c);

            $bestHand1 = 0;
            $bestHand2 = 0;
            foreach(new Combinations(array_merge($table, $cardCollections[1]), 5) as $hand){
                $result = $this->pokerEvaluator->getValueOfFive($hand[0],$hand[1],$hand[2],$hand[3],$hand[4]);
                if ($bestHand1 == 0 || $bestHand1 > $result) {
                    $bestHand1 = $result;
                }
            }
            foreach(new Combinations(array_merge($table, $cardCollections[2]), 5) as $hand){
                $result = $this->pokerEvaluator->getValueOfFive($hand[0],$hand[1],$hand[2],$hand[3],$hand[4]);
                if ($bestHand2 == 0 || $bestHand2 > $result) {
                    $bestHand2 = $result;
                }
            }
            if($bestHand1 < $bestHand2){
                $winsBySeat[1]++;
            } else if($bestHand1 > $bestHand2){
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
        $allCards = array_merge($dealtCards, $communityCards);

        if (sizeof($allCards) < 5) {
            return [];
        }

        $bestHand = null;
        $cardsForBestHand = null;
        foreach (new Combinations($allCards, 5) as $c) {
            $cards = $c;
            $value = $this->pokerEvaluator->getValue($cards);
            if ($bestHand == null || $bestHand > $value) {
                $bestHand = $value;
                $cardsForBestHand = $c;
            }
        }
        return $this->pokerEvaluator->getHandNameForBinaries($cardsForBestHand);
    }
}
