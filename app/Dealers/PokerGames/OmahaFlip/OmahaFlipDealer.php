<?php

namespace App\Dealers\PokerGames\OmahaFlip;

use App\Dealers\PokerGames\FourStreetGames\HoldemDealer;
use App\Dealers\PokerGames\FourStreetGames\HoldemOddsSolver;


class OmahaFlipDealer extends HoldemDealer
{

    private HoldemOddsSolver $oddsSolver;

    public function __construct() {
        parent::__construct();
        $this->oddsSolver = new OmahaHoldemOddsSolver();
    }

    protected function getHandCardCount(): int
    {
        return 4;
    }

    protected function getOddsSolver() : HoldemOddsSolver
    {
        return $this->oddsSolver;
    }
}
