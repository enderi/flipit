<?php

namespace App\Lib\DeckLib;

class card
{
    private $rank;
    private $rankName;
    private $rankValue;
    private $rankIntValue;

    private $suit;
    private $suitName;
    private $suitValue;
    private $suitIntValue;

    private static $ranks = array(
        /*'2' => array('value' => 2, 'int' => 0, 'name' => 'Two' ),
        '3' => array('value' => 3, 'int' => 1, 'name' => 'Three'),
        '4' => array('value' => 5, 'int' => 2, 'name' => 'Four'),
        '5' => array('value' => 7, 'int' => 3, 'name' => 'Five'),
        '6' => array('value' => 11, 'int' => 4, 'name' => 'Six'),
        '7' => array('value' => 13, 'int' =>5 , 'name' => 'Seven'),
        '8' => array('value' => 17, 'int' =>6 , 'name' => 'Eight'),
        '9' => array('value' => 19, 'int' =>7 , 'name' => 'Nine'),
        'T' => array('value' => 23, 'int' => 8, 'name' => 'Ten'),
        'J' => array('value' => 29, 'int' => 9, 'name' => 'Jack'),
        'Q' => array('value' => 31, 'int' => 10, 'name' => 'Queen'),
        'K' => array('value' => 37, 'int' => 11, 'name' => 'King'),
        'A' => array('value' => 41, 'int' => 12, 'name' => 'Ace')*/
        '2' => array('value' => 2, 'int' => 0, 'name' => 'Two'),
        '3' => array('value' => 3, 'int' => 1, 'name' => 'Three'),
        '4' => array('value' => 5, 'int' => 2, 'name' => 'Four'),
        '5' => array('value' => 7, 'int' => 3, 'name' => 'Five'),
        '6' => array('value' => 9, 'int' => 4, 'name' => 'Six'),
        '7' => array('value' => 11, 'int' => 5, 'name' => 'Seven'),
        '8' => array('value' => 13, 'int' => 6, 'name' => 'Eight'),
        '9' => array('value' => 17, 'int' => 7, 'name' => 'Nine'),
        'T' => array('value' => 19, 'int' => 8, 'name' => 'Ten'),
        'J' => array('value' => 23, 'int' => 9, 'name' => 'Jack'),
        'Q' => array('value' => 29, 'int' => 10, 'name' => 'Queen'),
        'K' => array('value' => 31, 'int' => 11, 'name' => 'King'),
        'A' => array('value' => 37, 'int' => 12, 'name' => 'Ace')
    );
    private static $suits = array(
        'c' => array('value' => 41, 'int' => 1, 'name' => 'Clubs'),
        'h' => array('value' => 43, 'int' => 2, 'name' => 'Hearts'),
        'd' => array('value' => 47, 'int' => 4, 'name' => 'Diamonds'),
        's' => array('value' => 53, 'int' => 8, 'name' => 'Spades')
    );

    public function __construct($rank, $suit)
    {
        $this->rank = $rank;
        $this->rankValue = self::$ranks[$rank]["value"];
        $this->rankIntValue = self::$ranks[$rank]["int"];
        $this->rankName = self::$ranks[$rank]["name"];

        $this->suit = $suit;
        $this->suitValue = self::$suits[$suit]["value"];
        $this->suitIntValue = self::$suits[$suit]["int"];
        $this->suitName = self::$suits[$suit]["name"];
    }

    public static function of($strName) {
        return new card($strName[0], $strName[1]);
    }

    public function getRank()
    {
        return $this->rank;
    }

    public function getSuit()
    {
        return $this->suit;
    }

    public function toString()
    {
        return $this->rank . $this->suit;
    }

    public static function getRanks()
    {
        return array_keys(self::$ranks);
    }

    public static function getSuits()
    {
        return array_keys(self::$suits);
    }

    public function getSuitName()
    {
        return $this->suitName;
    }

    public function getSuitValue()
    {
        return $this->suitValue;
    }

    public function getSuitIntValue()
    {
        return $this->suitIntValue;
    }

    public function getRankName()
    {
        return $this->rankName;
    }

    public function getRankValue()
    {
        return $this->rankValue;
    }

    public function getRankIntValue()
    {
        return $this->rankIntValue;
    }
}

?>
