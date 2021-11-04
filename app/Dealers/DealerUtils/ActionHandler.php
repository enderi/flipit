<?php

namespace App\Dealers\DealerUtils;


use App\Dealers\Exceptions\InvalidActionException;

class ActionHandler
{
    private array $actionsBySeat;

    public function __construct($seats) {
        $this->actionsBySeat = [];
        foreach($seats as $seat) {
            $this->actionsBySeat[$seat] = collect();
        }
    }

    public function addOption($seat, $key) {
        if(!array_key_exists($seat, $this->actionsBySeat)){
            throw new InvalidActionException("Seat " . $seat . " was not expected");
        }
        $optionList = $this->actionsBySeat[$seat];
        if($optionList->contains($key)){
            throw new InvalidActionException("Option '" . $key . "' already exists");
        }
        $this->actionsBySeat[$seat]->push($key);
    }

    public function addOptionForAll($key) {
        foreach ($this->actionsBySeat as $seat => $item) {
            $this->addOption($seat, $key);
        }
    }

    public function playerActed($seat, $action) {
        if(!array_key_exists($seat, $this->actionsBySeat) || !$this->actionsBySeat[$seat]->contains($action)){
            throw new InvalidActionException("Seat ". $seat . " or key '".$action."' not found");
        }
        $this->actionsBySeat[$seat] = $this->actionsBySeat[$seat]->reject($action);
    }

    public function getOptions() {
        return $this->actionsBySeat;
    }

    public function isBlocked() {
        foreach ($this->actionsBySeat as $seat => $actions) {
            if(count($actions) > 0) {
                return true;
            }
        }
        return false;
    }
}
