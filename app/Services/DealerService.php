<?php

namespace App\Services;

use App\Dealers\CrazyPineapple\CrazyPineappleDealer;
use App\Dealers\DealerBase;
use App\Dealers\OmahaFlip\OmahaFlipDealer;
use App\Dealers\TexasFlip\Combinations;
use App\Dealers\TexasFlip\TexasFlipDealer;
use App\Dealers\TrickGame\LastTrickDealer;
use App\Models\Game;

class DealerService {
    public function getDealer($game) :DealerBase {
        if ($game->game_type == TexasFlipDealer::TEXAS_FLIP) {
            $dealer = TexasFlipDealer::of($game);
        } else if ($game->game_type == OmahaFlipDealer::OMAHA_FLIP) {
            $dealer = OmahaFlipDealer::of($game);
        } elseif ($game->game_type == CrazyPineappleDealer::CRAZY_PINEAPPLE) {
            $dealer = CrazyPineappleDealer::of($game);
        } else if ($game->game_type == LastTrickDealer::LAST_TRICK) {
            $dealer = LastTrickDealer::of($game);
        }
        return $dealer;
    }

    public function getDealerForUuid($gameUuid) :DealerBase {
        $game = Game::firstWhere('uuid', $gameUuid);
        return $this->getDealer($game);
    }

    public function calc($deck, $table, $hand1, $hand2) {
        $winsFor1 = 0;
        $winsFor2 = 0;
        $ties = 0;
        $pokerank = new Pokerank();
        $pokerank->setLookup(Lookup::lookup());
        $remainingCards = 5 - sizeof($table);
        foreach (new Combinations($deck->getCards(), $remainingCards) as $c) {
            $resultFor1 = $this->getBestTexasHand($pokerank, $hand1, $table->merge($c));
            $resultFor2 = $this->getBestTexasHand($pokerank, $hand2, $table->merge($c));
            if ($resultFor1 < $resultFor2) {
                $winsFor1++;
            }
            if ($resultFor2 < $resultFor1) {
                $winsFor2++;
            }
            if ($resultFor1 == $resultFor2) {
                $ties++;
            }
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
            $card1 = $pokerank->toInt($c[0]->getSuitIntValue(), $c[0]->getRankIntValue());
            $card2 = $pokerank->toInt($c[1]->getSuitIntValue(), $c[1]->getRankIntValue());
            $card3 = $pokerank->toInt($c[2]->getSuitIntValue(), $c[2]->getRankIntValue());
            $card4 = $pokerank->toInt($c[3]->getSuitIntValue(), $c[3]->getRankIntValue());
            $card5 = $pokerank->toInt($c[4]->getSuitIntValue(), $c[4]->getRankIntValue());
            $value = $pokerank->score($card1, $card2, $card3, $card4, $card5);
            if ($bestHand == null || $bestHand > $value) {
                $bestHand = $value;
            }
        }
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
                if ($bestHand == null || $bestHand < $value) {
                    $bestHand = $value;
                }
            }
        }

        return $bestHand;
    }
}
