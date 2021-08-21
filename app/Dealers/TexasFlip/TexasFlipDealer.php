<?php

namespace App\Dealers\TexasFlip;

use App\Dealers\FourStreetGames\HoldemBaseDealer;
use App\Lib\DeckLib\card;
use App\Lib\DeckLib\Deck;
use App\Lib\DeckLib\evaluate;
use App\Services\Lookup;
use App\Services\Pokerank;

class TexasFlipDealer extends HoldemBaseDealer
{
    const TEXAS_FLIP = 'TEXAS-FLIP';
    const POCKET_CARD_COUNT = 2;

    public function getGameType(): string
    {
        return self::TEXAS_FLIP;
    }

    public function getCardCount()
    {
        return self::POCKET_CARD_COUNT;
    }

    public static function of($game): TexasFlipDealer
    {
        $result = new TexasFlipDealer();
        $result->initWithGame($game);
        return $result;
    }

    protected function getHandValues($handCards, $communityCards)
    {
        return $this->getBestHand($handCards, $communityCards);
    }

    protected function getBestHand($handCards, $communityCards): array
    {
        $evaluator = new evaluate();
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
                $cards[] = card::of($cardForThis);
            }
            $value = $evaluator->getValue($cards);
            $name = $evaluator->getHandName();
            if ($bestHand == null || $bestHand['value'] > $value) {
                $bestHand = [
                    'value' => $value,
                    'cards' => $c,
                    'name' => $name
                ];
            }
        }
        return $bestHand;
    }

    protected function getOddsUntilRiver($handCards, Deck $deck) {
        $cardsLeft = 5 - count($handCards['community']);
        $winsBySeat = [
            1=>0,
            2=>0,
            'tie'=>0,
            'total'=>0
        ];
        $counter = 0;
        $pokerank = new Pokerank();
        $pokerank->setLookup(Lookup::lookup());
        $cardCollections = [];
        foreach ($handCards as $key => $c) {
            $cardCollections[$key] = collect($c)->map(function($c) use ($pokerank){
                return $pokerank->fromString($c);
            });
        }
        $remainingCards = collect($deck->getCards())->map(function($c) use($pokerank){
            return $pokerank->fromString($c->toString());

        })->toArray();
        foreach(new Combinations($remainingCards, $cardsLeft) as $c) {
            $table = $cardCollections['community']
                ->merge(collect($c));

            $bestHand1 = 0;
            $bestHand2 = 0;
            foreach(new Combinations($table->merge($cardCollections[1])->toArray(), 5) as $hand){
                $result = $pokerank->score($hand[0],$hand[1],$hand[2],$hand[3],$hand[4]);
                if($bestHand1 < $result){
                    $bestHand1 = $result;
                }
            }
            foreach(new Combinations($table->merge($cardCollections[2])->toArray(), 5) as $hand){
                $result = $pokerank->score($hand[0],$hand[1],$hand[2],$hand[3],$hand[4]);
                if($bestHand2 < $result){
                    $bestHand2 = $result;
                }
            }
            if($bestHand1 > $bestHand2){
                $winsBySeat[1]++;
            } else if($bestHand1 < $bestHand2){
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
