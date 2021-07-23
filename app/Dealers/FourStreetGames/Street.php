<?php

namespace App\Dealers\FourStreetGames;

class Street {
    private $streetName;
    private $actions;

    public function __construct($streetName) {
        $this->actions = collect([]);
        $this->streetName = $streetName;
    }

    public function getStreetName () {

        return $this->streetName;
    }

    public function addAction($action) {
        $this->actions->push($action);
    }


}
