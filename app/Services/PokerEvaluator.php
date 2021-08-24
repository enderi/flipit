<?php

namespace App\Services;

use App\DomainObjects\Arrays\Arrays;
use App\Lib\DeckLib\card;
use App\Lib\DeckLib\HandRank;

class PokerEvaluator
{
    var $rankings = array();
    var $done = 1;
    var $cards = array();

    var $highest;
    var $besthand;


    private $indexLookup;
    private $productValues;
    private $uniq5;
    private $prodVals;

    private $hashValues;
    private $hashAdjust;


    function __construct()
    {
        $this->indexLookup = Arrays::$indexValues;
        $this->productValues = Arrays::$productValues;
        $this->uniq5 = [];
        for($i=0; $i < sizeof(Arrays::$unique5); $i++) {
            $this->uniq5[$i] = Arrays::$unique5[$i];
        }
        $prodVals = [];
        for($i=0; $i < sizeof(Arrays::$productValues); $i++) {
            $prodVals[Arrays::$productValues[$i]] = Arrays::$indexValues[$i];
        }

        $this->hashAdjust = Arrays::$hash_adjust;
        $this->hashValues = Arrays::$hash_values;
        $this->prodVals = $prodVals;
        $this->cards = array(
            new card('2','d'),
            new Card('3','d'),
            new Card('4','d'),
            new Card('5','d'),
            new Card('6','d'),
            new Card('7','d'),
            new Card('8','d'),
            new Card('9','d'),
            new Card('T','d'),
            new Card('J','d'),
            new Card('Q','d'),
            new Card('K','d'),
            new Card('A','d')
        );

        $this->straights(true);
        $this->quads();
        $this->fullhouses();
        $this->flushes(true);
        $this->straights();
        $this->trips();
        $this->twopairs();
        $this->pairs();
        $this->flushes();
    }

    function pairs()
    {
        for ($a = 12; $a >= 0; $a--)
        {
            for ($b = 12; $b >= 0; $b--)
            {
                for ($c = $b - 1; $c >= 0; $c--)
                {
                    for ($d = $c - 1; $d >= 0; $d--)
                    {
                        if ($a != $b && $a != $c && $a != $d && $b != $c && $b != $d && $c != $d)
                        {
                            $this->addRanking(
                                array(
                                    $this->cards[$a],
                                    $this->cards[$a],
                                    $this->cards[$b],
                                    $this->cards[$c],
                                    $this->cards[$d]
                                )
                            );
                        }
                    }

                }
            }
        }

    }

    function addRanking($cards, $suited=false)
    {
        $value = 1;

        foreach ($cards as $card)
        {
            $value *= $card->getRankValue();
        }

        if ($suited) $value *= 61;

        $this->rankings[$value]=$this->done;

        $this->done++;
    }

    function twopairs()
    {
        for ($a = 12; $a >= 0; $a--)
        {
            for ($b = $a - 1; $b >= 0; $b--)
            {
                for ($c = 12; $c >= 0; $c--)
                {
                    if ($a != $b && $a != $c && $b != $c)
                    {
                        $this->addRanking(
                            array(
                                $this->cards[$a],
                                $this->cards[$a],
                                $this->cards[$b],
                                $this->cards[$b],
                                $this->cards[$c]
                            )
                        );
                    }
                }
            }
        }
    }

    function trips()
    {
        for ($a = 12; $a >= 0; $a--)
        {
            for ($b = 12; $b >= 0; $b--)
            {
                for ($c = $b - 1; $c >= 0; $c--)
                {
                    if ($a != $b && $a != $c)
                    {
                        $this->addRanking(
                            array(
                                $this->cards[$a],
                                $this->cards[$a],
                                $this->cards[$a],
                                $this->cards[$b],
                                $this->cards[$c]
                            )
                        );
                    }
                }
            }
        }
    }

    function flushes($suited=false)
    {
        for ($a = 12; $a >= 0; $a--)
        {
            for ($b = $a - 1; $b >= 0; $b--)
            {
                for ($c = $b - 1; $c >= 0; $c--)
                {
                    for ($d = $c - 1; $d >= 0; $d--)
                    {
                        for ($e = $d - 1; $e >= 0; $e--)
                        {
                            if ($a - 4 != $e)
                            {
                                $cards = array(
                                    $this->cards[$a],
                                    $this->cards[$b],
                                    $this->cards[$c],
                                    $this->cards[$d],
                                    $this->cards[$e]
                                );

                                if ($cards[0]->getRankValue() * $cards[1]->getRankValue() * $cards[2]->getRankValue() * $cards[3]->getRankValue() * $cards[4]->getRankValue()  != 8610) // filter the awkward A5432 combination
                                {
                                    $this->addRanking($cards, $suited);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    function fullhouses()
    {
        for ($i = 12; $i >= 0; $i--)
        {
            for ($k = 12; $k >= 0; $k--)
            {
                if ($k != $i)
                {
                    $this->addRanking(
                        array(
                            $this->cards[$i],
                            $this->cards[$i],
                            $this->cards[$i],
                            $this->cards[$k],
                            $this->cards[$k]
                        )
                    );
                }
            }
        }
    }

    function quads()
    {
        for ($i = 12; $i >= 0; $i--)
        {
            for ($k = 12; $k >= 0; $k--)
            {
                if ($k != $i)
                {
                    $this->addRanking(
                        array(
                            $this->cards[$i],
                            $this->cards[$i],
                            $this->cards[$i],
                            $this->cards[$i],
                            $this->cards[$k]
                        )
                    );

                }
            }
        }
    }

    function straights($suited=false)
    {
        for ($i = 12; $i > 2; $i--)
        {
            $cards = array(
                $this->cards[$i],
                $this->cards[$i-1],
                $this->cards[$i-2],
                $this->cards[$i-3]
            );

            if ($i > 3)
            {
                $cards[]=$this->cards[$i-4];
            }
            else
            {
                $cards[]=new Card('A','d');
            }

            $this->addRanking($cards, $suited);
        }
    }

    public function getValueOfFive($c1, $c2, $c3, $c4, $c5)
    {
        $bitshifted = ($c1 | $c2 | $c3 | $c4 | $c5) >> 16;

        if((0xF000 & $c1 & $c2 & $c3 & $c4 & $c5) != 0) // same suit?
        {
            return Arrays::$flushes[$bitshifted];
        }

        $s = $this->uniq5[$bitshifted];
        if($s != 0) {
            return $s;
        }
        $q = ($c1 & 0xff) * ($c2 & 0xff) * ($c3 & 0xff) * ($c4 & 0xff) * ($c5 & 0xff);
        return $this->prodVals[$q];
    }



    function getValue($cards)
    {
        if (count($cards) < 5)
        {
            return false;
        }
        else
        {
            $count = 0;
            $highest = 1000000;

            for ($a = count($cards) - 1; $a >= 0; $a--)
            {
                for ($b = $a - 1; $b >= 0; $b--)
                {
                    for ($c = $b - 1; $c >= 0; $c--)
                    {
                        for ($d = $c - 1; $d >= 0; $d--)
                        {
                            for ($e = $d - 1; $e >= 0; $e--)
                            {
                                $rank = $this->getValueOfFive(
                                    $cards[$a],
                                    $cards[$b],
                                    $cards[$c],
                                    $cards[$d],
                                    $cards[$e]);
                                $count++;
                                //echo 'Rank: ' . $rank . "\n";
                                if ($rank < $highest) // lowest rank is best, 1 = Royal Flush.
                                {
                                    $currentHand = array(
                                        $cards[$a],
                                        $cards[$b],
                                        $cards[$c],
                                        $cards[$d],
                                        $cards[$e]
                                    );
                                    $highest = $rank;
                                    $this->bestHand = $currentHand;
                                }
                            }
                        }
                    }
                }
            }
            return $highest;
        }

    }

    static function sortCardsByRanks($cards)
    {
        usort($cards, function($a, $b) use ($cards)
        {
            $aFreq = count(array_filter( $cards, function($object) use ($a) { return $object->getRank() == $a->getRank(); })); // count how many cards of that rank exist in the array
            $bFreq = count(array_filter( $cards, function($object) use ($b) { return $object->getRank() == $b->getRank(); }));

            //echo "aFreq: " .$a->getRank(). " - $aFreq, bFreq: " .$b->getRank(). " - $bFreq" . PHP_EOL;

            if ($bFreq > $aFreq || $aFreq > $bFreq)
            {
                return ($bFreq > $aFreq) ? 1: -1;
            }
            else
            {
                return ($a->getRankValue($a->getRank()) < $b->getRankValue($b->getRank())) ? 1: -1;
            }
        });

        return $cards;
    }

    public function getHandName() : HandRank
    {
        return $this->calculateHandName($this->highest, $this->bestHand);
    }

    public function calculateHandName($rank, $cards) : HandRank
    {
        $cards = self::sortCardsByRanks($cards);

        if (in_array($rank, array(10, 1609))) // When it's A5432, we need to bump the ace to the end of the array.
        {
            $card = array_shift($cards);
            $cards[]=$card;
        }

        if ($rank == 1)
        {
            return new HandRank('Royal Flush', '');
        }
        else if ($rank >= 2 && $rank <= 10)
        {
            return new HandRank("Straight Flush",$cards[0]->getRankName() . " high");
        }
        else if ($rank >= 11 && $rank <= 166)
        {
            return new HandRank("Four of a Kind",$cards[0]->getRankName() . "s with a " . $cards[4]->getRankName() . " kicker");
        }
        else if ($rank >= 167 && $rank <= 322)
        {
            return new HandRank("Full House",$cards[0]->getRankName() . "s full of " . $cards[4]->getRankName() . "s");
        }
        else if ($rank >= 323 && $rank <= 1599)
        {
            return new HandRank("Flush",$cards[0]->getRankName() . " high - " . $cards[0]->getRankName() . ", " . $cards[1]->getRankName() . ", " . $cards[2]->getRankName() . ", " . $cards[3]->getRankName() . ", " . $cards[4]->getRankName());
        }
        else if ($rank >= 1600 && $rank <= 1609)
        {
            return new HandRank("Straight",$cards[0]->getRankName() . " high - " . $cards[0]->getRankName() . ", " . $cards[1]->getRankName() . ", " . $cards[2]->getRankName() . ", " . $cards[3]->getRankName() . ", " . $cards[4]->getRankName());
        }
        else if ($rank >= 1610 && $rank <= 2467)
        {
            return new HandRank("Three of a Kind",$cards[0]->getRankName() . "s with " . $cards[3]->getRankName() . " and " .  $cards[4]->getRankName() . " kickers");
        }
        else if ($rank >= 2468 && $rank <= 3325)
        {
            return new HandRank("Two pair",$cards[0]->getRankName() . "s and " . $cards[2]->getRankName() . "s with a " .  $cards[4]->getRankName() . " kicker");
        }
        else if ($rank >= 3326 && $rank <= 6185)
        {
            return new HandRank("One Pair",$cards[0]->getRankName() . "s with " . $cards[2]->getRankName() . ", " . $cards[3]->getRankName() . ", " . $cards[4]->getRankName() . " kickers");
        }
        else if ($rank >= 6186)
        {
            return new HandRank("High Card",$cards[0]->getRankName() . ", ". $cards[0]->getRankName() . ", " . $cards[1]->getRankName() . ", " . $cards[2]->getRankName() . ", " . $cards[3]->getRankName() . ", " . $cards[4]->getRankName());
        }
    }
}

?>
