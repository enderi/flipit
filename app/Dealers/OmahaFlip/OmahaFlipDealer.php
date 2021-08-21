<?php

namespace App\Dealers\OmahaFlip;

use App\Dealers\FourStreetGames\HoldemBaseDealer;
use App\Dealers\TexasFlip\Combinations;
use App\Lib\DeckLib\card;
use App\Lib\DeckLib\Deck;
use App\Lib\DeckLib\evaluate;
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

    protected function getOddsUntilRiver($handCards, Deck $deck)
    {
        $cardsInDeck = collect($deck->getCards())->map(function($c) { return $c->toString();})->toArray();

        $cardsLeft = 5 - count($handCards['community']);
        $winsBySeat = [
            1 => 0,
            2 => 0,
            'tie' => 0,
            'total' => 0
        ];
        $counter = 0;
        $pokerank = new Pokerank();
        $pokerank->setLookup($pokerank->createLookup());
        $cardCollections = [];
        foreach ($handCards as $key => $c) {
            $cardCollections[$key] = collect($c)->toArray();
        }
        $remainingCards = collect($deck->getCards())->map(function ($c) use ($pokerank) {
            return $pokerank->fromString($c->toString());
        })->toArray();
        $pokerEvaluator = new PokerEvaluator();
        foreach (new Combinations($cardsInDeck, $cardsLeft) as $c) {
            $table = $handCards['community']
                ->merge(collect($c));
            $bestHand1 = 0;
            $bestHand2 = 0;
            $b1 = null;
            $b2 = null;
            foreach (new Combinations($table->toArray(), 3) as $tableCombination) {
                $mapped1 = $this->mapToInts($tableCombination);
                foreach (new Combinations($cardCollections[1], 2) as $handCard) {
                    $mapped2= $this->mapToInts($handCard);

                    $result = $pokerank->score($mapped1[0], $mapped1[1], $mapped1[2], $mapped2[0], $mapped2[1]);
                    $result2 = $pokerEvaluator->getValueOfFive($mapped1[0], $mapped1[1], $mapped1[2], $mapped2[0], $mapped2[1]);
                    if ($bestHand1 == 0 || $bestHand1 > $result2) {
                        $b1 = $handCard;
                        $bestHand1 = $result2;
                    }
                    //echo json_encode($tableCombination) . ', ' . json_encode($handCard) . ' => ' . $result2 . ', ' . json_encode($b1) . '<br>';
                }
                foreach (new Combinations($cardCollections[2], 2) as $handCard) {
                    $mapped2= $this->mapToInts($handCard);

                    $result = $pokerank->score($mapped1[0], $mapped1[1], $mapped1[2], $mapped2[0], $mapped2[1]);
                    $result2 = $pokerEvaluator->getValueOfFive($mapped1[0], $mapped1[1], $mapped1[2], $mapped2[0], $mapped2[1]);
                    if ($bestHand2 == 0 || $bestHand2 > $result2) {
                        $b2 = $handCard;
                        $bestHand2 = $result2;
                    }
                }
            }
            if ($bestHand1 < $bestHand2) {
                $winsBySeat[1]++;
            } else if ($bestHand1 > $bestHand2) {
                //echo 'tableCombination 2 win: '. $bestHand1 . ' < ' . $bestHand2 . ' => ' . json_encode($table) . ', ' . json_encode($b1) . ', ' . json_encode($b2) . "<br>";
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
    protected function mapToInts($hand): \Illuminate\Support\Collection
    {
        $mapped = collect($hand)->map(function ($card) {
            $c = Card::of($card);
            return $c->getBinaryValue();
        });
        return $mapped;
    }
}
