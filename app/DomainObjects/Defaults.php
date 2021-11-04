<?php

namespace App\DomainObjects;

use App\Dealers\PokerGames\CrazyPineapple\CrazyPineappleDealer;
use App\Dealers\PokerGames\OmahaFlip\OmahaFlipDealer;
use App\Dealers\PokerGames\FourStreetGames\TexasFlipDealer;

class Defaults {
    public static $gameInfo = [
        'TEXAS-FLIP' => [
            'seats' => 2,
            'dealer' => TexasFlipDealer::class
        ],
        'OMAHA-FLIP' => [
            'seats' => 2,
            'dealer' => OmahaFlipDealer::class
        ],
        'CRAZY-PINEAPPLE' => [
            'seats' => 2,
            'dealer' => CrazyPineappleDealer::class
        ]
    ];
}
