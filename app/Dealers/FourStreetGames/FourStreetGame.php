<?php

class FourStreetGame {
    private $players;

    private $joining;

    private $preflop;
    private $flop;
    private $river;

    private function __construct()
    {
        $this->players = [];
        $this->joining = new Street();
    }

}