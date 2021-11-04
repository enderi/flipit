<?php

namespace App\Dealers\PokerGames\TexasFlip;

use App\Dealers\PokerGames\FourStreetGames\HoldemDealer;
use App\Dealers\PokerGames\FourStreetGames\HoldemOddsSolver;

class TexasFlipDealer extends HoldemDealer
{

    private HoldemOddsSolver $oddsSolver;

    public function __construct() {
        parent::__construct();
        $this->oddsSolver = new TexasHoldemOddsSolver();
    }

    protected function getHandCardCount(): int
    {
        return 2;
    }

    protected function getOddsSolver() : HoldemOddsSolver
    {
        return $this->oddsSolver;
    }
}
