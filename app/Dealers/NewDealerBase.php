<?php

namespace App\Dealers;

abstract class NewDealerBase
{

    private $players = [];
    private $actions = [];

    public function __construct($players, $actions) {
        foreach($players as $player) {
            $this->addPlayer($player);
        }
        foreach($actions as $action) {
            $this->addAction($action);
        }
    }

    private function addPlayer($player) {
        $this->players[] = $player;
    }

    private function addAction($action) {
        $this->actions[] = $action;
    }
}
