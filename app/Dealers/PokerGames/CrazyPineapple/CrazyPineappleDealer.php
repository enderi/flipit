<?php

namespace App\Dealers\PokerGames\CrazyPineapple;
use App\Dealers\PokerGames\FourStreetGames\HoldemBaseDealer;
use App\Dealers\PokerGames\PokerEvaluator;
use App\DomainObjects\Combinations;
use App\DomainObjects\Deck;

class CrazyPineappleDealer extends HoldemBaseDealer
{
    const CRAZY_PINEAPPLE = 'CRAZY-PINEAPPLE';
    const POCKET_CARD_COUNT = 3;

    public function getGameType(): string
    {
        return self::CRAZY_PINEAPPLE;
    }

    public function getCardCount()
    {
        return self::POCKET_CARD_COUNT;
    }

    public static function of($game): CrazyPineappleDealer
    {
        $result = new CrazyPineappleDealer();
        $result->initWithGame($game);
        return $result;
    }

    protected function getHandValues($handCards, $communityCards)
    {
        return $this->getBestHand($handCards, $communityCards);
    }

    protected function getBestHand($handCards, $communityCards): array
    {
        $evaluator = new PokerEvaluator();
        $playerCardsInUse = [];
        foreach ($communityCards as $c) {
            $playerCardsInUse[] = $c;
        }

        foreach ($handCards as $c) {
            $playerCardsInUse[] = $c;
        }
        if (sizeof($playerCardsInUse) < 5) {
            return [];
        }
        $bestHand = null;
        foreach (new Combinations($playerCardsInUse, min(sizeof($playerCardsInUse), 5)) as $c) {
            $cards = [];
            foreach ($c as $cardForThis) {
                $cards[] = Card::of($cardForThis);
            }
            $value = $evaluator->getValue($cards);
            $name = $evaluator->getHandName();
            if ($bestHand == null || $bestHand['value'] > $value) {
                $bestHand = [
                    'value' => $value,
                    'cards' => $c,
                    'info' => $name
                ];
            }
        }
        return $bestHand;
    }

    protected function getOddsUntilRiver($handCards, Deck $deck) {
        $pokerEvaluator = new PokerEvaluator();
        $cardsInDeck = $deck->getCardIntValues();
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
            $cardCollections[$key] = $this->mapToInts($c);
        }

        foreach(new Combinations($cardsInDeck, $cardsLeft) as $c) {
            $table = array_merge($cardCollections['community'], $c);

            $bestHand1 = 0;
            $bestHand2 = 0;
            foreach(new Combinations(array_merge($table, $cardCollections[1]), 5) as $hand){
                $result = $pokerEvaluator->getValueOfFive($hand[0],$hand[1],$hand[2],$hand[3],$hand[4]);
                if ($bestHand1 == 0 || $bestHand1 > $result) {
                    $bestHand1 = $result;
                }
            }
            foreach(new Combinations(array_merge($table, $cardCollections[2]), 5) as $hand){
                $result = $pokerEvaluator->getValueOfFive($hand[0],$hand[1],$hand[2],$hand[3],$hand[4]);
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
}
