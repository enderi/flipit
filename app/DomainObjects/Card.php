<?php

namespace App\DomainObjects;

class Card
{
    private $rank;
    private $suit;

    private static $ranks = array(
        '2' => array('prime' => 2, 'index' => 0, 'name' => 'Two'),
        '3' => array('prime' => 3, 'index' => 1, 'name' => 'Three'),
        '4' => array('prime' => 5, 'index' => 2, 'name' => 'Four'),
        '5' => array('prime' => 7, 'index' => 3, 'name' => 'Five'),
        '6' => array('prime' => 11, 'index' => 4, 'name' => 'Six'),
        '7' => array('prime' => 13, 'index' => 5, 'name' => 'Seven'),
        '8' => array('prime' => 17, 'index' => 6, 'name' => 'Eight'),
        '9' => array('prime' => 19, 'index' => 7, 'name' => 'Nine'),
        'T' => array('prime' => 23, 'index' => 8, 'name' => 'Ten'),
        'J' => array('prime' => 29, 'index' => 9, 'name' => 'Jack'),
        'Q' => array('prime' => 31, 'index' => 10, 'name' => 'Queen'),
        'K' => array('prime' => 37, 'index' => 11, 'name' => 'King'),
        'A' => array('prime' => 41, 'index' => 12, 'name' => 'Ace')
    );
    private static $suits = array(
        'c' => array('prime' => 43, 'intValue' => 32768, 'name' => 'Clubs'),
        'h' => array('prime' => 47, 'intValue' => 8192, 'name' => 'Hearts'),
        'd' => array('prime' => 53, 'intValue' => 16384, 'name' => 'Diamonds'),
        's' => array('prime' => 59, 'intValue' => 4096, 'name' => 'Spades')
    );

    private static $binValues = [
        '98306' => '2c',
        '164099' => '3c',
        '295429' => '4c',
        '557831' => '5c',
        '1082379' => '6c',
        '2131213' => '7c',
        '4228625' => '8c',
        '8423187' => '9c',
        '16812055' => 'Tc',
        '33589533' => 'Jc',
        '67144223' => 'Qc',
        '134253349' => 'Kc',
        '268471337' => 'Ac',
        '73730' => '2h',
        '139523' => '3h',
        '270853' => '4h',
        '533255' => '5h',
        '1057803' => '6h',
        '2106637' => '7h',
        '4204049' => '8h',
        '8398611' => '9h',
        '16787479' => 'Th',
        '33564957' => 'Jh',
        '67119647' => 'Qh',
        '134228773' => 'Kh',
        '268446761' => 'Ah',
        '81922' => '2d',
        '147715' => '3d',
        '279045' => '4d',
        '541447' => '5d',
        '1065995' => '6d',
        '2114829' => '7d',
        '4212241' => '8d',
        '8406803' => '9d',
        '16795671' => 'Td',
        '33573149' => 'Jd',
        '67127839' => 'Qd',
        '134236965' => 'Kd',
        '268454953' => 'Ad',
        '69634' => '2s',
        '135427' => '3s',
        '266757' => '4s',
        '529159' => '5s',
        '1053707' => '6s',
        '2102541' => '7s',
        '4199953' => '8s',
        '8394515' => '9s',
        '16783383' => 'Ts',
        '33560861' => 'Js',
        '67115551' => 'Qs',
        '134224677' => 'Ks',
        '268442665' => 'As'
    ];

    private $binaryValue;


    public function __construct($rank, $suit)
    {
        $this->rank = $rank;
        $this->suit = $suit;
        $this->buildBinaryValue(self::$ranks[$rank]['index'], self::$ranks[$rank]['prime'], self::$suits[$suit]['intValue']);
    }

    public static function fromBinary($binaryVal): Card
    {
        return Card::of(self::$binValues[$binaryVal]);

    }

    public static function of($strName)
    {
        return new Card($strName[0], $strName[1]);
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


    public function getRankName()
    {
        return self::$ranks[$this->rank]['name'];
    }

    public function getRankValue()
    {
        return self::$ranks[$this->rank]['prime'];
    }

    public function getBinaryValue()
    {
        return $this->binaryValue;
    }

    private function buildBinaryValue($j, $prime, $suit)
    {
        $this->binaryValue = $prime | ($j << 8) | $suit | (1 << (16 + $j));
        $this->rankVal = $this->binaryValue >> 16;
    }

    public function __toString() {
        return 'hee';
    }
}

?>
