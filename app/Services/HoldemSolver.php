<?php

namespace App\Services;

use App\Dealers\TexasFlip\Combinations;

class HoldemSolver {
    public function calc($cardsToCome, $table, $hand1, $hand2) {
        $winsFor1 = 0;
        $winsFor2 = 0;
        $ties = 0;
        $pokerank = new Pokerank();
        $pokerank->setLookup(Lookup::lookup());
        $remainingCards = 5 - sizeof($table);
        
        foreach (new Combinations($cardsToCome, $remainingCards) as $c) {
            $fullTable = $table->merge($c);
            
            $resultFor1 = $this->getBestTexasHand($pokerank, $hand1, $fullTable);
            $resultFor2 = $this->getBestTexasHand($pokerank, $hand2, $fullTable);
            if ($resultFor1 < $resultFor2) {
                echo '1 wins ';
                $winsFor1++;
            }
            if ($resultFor2 < $resultFor1) {
                echo '2 wins ';
                $winsFor2++;
            }
            if ($resultFor1 == $resultFor2) {
                echo 'tie ';
                $ties++;
            }
            echo ' when dealt ' . $c[0]->toString() . ' ' . $c[1]->toString() . '<br>';
            
        }
        return [
            '1' => $winsFor1,
            '2' => $winsFor2,
            '0' => $ties
        ];
    }

    public function getBestTexasHand($pokerank, $handCards, $communityCards)
    {
        $playerCardsInUse = [];
        foreach ($handCards as $c) {
            $playerCardsInUse[] = $c;
        }
        foreach ($communityCards as $c) {
            $playerCardsInUse[] = $c;
        }
        if (sizeof($playerCardsInUse) < 5) {
            return [];
        }
        $bestHand = null;
        foreach (new Combinations($playerCardsInUse, min(sizeof($playerCardsInUse), 5)) as $c) {
            $card1 = $pokerank->toInt($c[0]->getSuitIntValue(), $c[0]->getRankIntValue());
            $card2 = $pokerank->toInt($c[1]->getSuitIntValue(), $c[1]->getRankIntValue());
            $card3 = $pokerank->toInt($c[2]->getSuitIntValue(), $c[2]->getRankIntValue());
            $card4 = $pokerank->toInt($c[3]->getSuitIntValue(), $c[3]->getRankIntValue());
            $card5 = $pokerank->toInt($c[4]->getSuitIntValue(), $c[4]->getRankIntValue());
            $value = $pokerank->score($card1, $card2, $card3, $card4, $card5);
            echo $value . ' - ' . $bestHand . ' => ' .collect($c)->map(function ($c) {
                return $c->toString();
            }) . '<br>';
            if ($bestHand == null || $bestHand > $value) {
                $bestHand = $value;
            }
        }
        dd($communityCards);
        return $bestHand;
    }

    public function getBestOmahaHand($pokerank, $handCards, $communityCards)
    {
        $handCombinations = [];
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

                $card1 = $pokerank->toInt($hand[0]->getSuitIntValue(), $hand[0]->getRankIntValue());
                $card2 = $pokerank->toInt($hand[1]->getSuitIntValue(), $hand[1]->getRankIntValue());
                $card3 = $pokerank->toInt($hand[2]->getSuitIntValue(), $hand[2]->getRankIntValue());
                $card4 = $pokerank->toInt($hand[3]->getSuitIntValue(), $hand[3]->getRankIntValue());
                $card5 = $pokerank->toInt($hand[4]->getSuitIntValue(), $hand[4]->getRankIntValue());

                $value = $pokerank->score($card1, $card2, $card3, $card4, $card5);
                if ($bestHand == null || $bestHand > $value) {
                    $bestHand = $value;
                }
            }
        }

        return $bestHand;
    }
}
