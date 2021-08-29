<?php

namespace App\Dealers\DealerUtils;


use App\Dealers\Exceptions\InvalidActionException;

class ActionHandler
{
    private array $actionsBySeat;

    public function __construct($seats) {
        $this->actionsBySeat = [];
        foreach($seats as $seat) {
            $this->actionsBySeat[$seat] = [];
        }
    }

    public function addOption($seat, $key) {
        if(!array_key_exists($seat, $this->actionsBySeat)){
            throw new InvalidActionException("Seat was not expected");
        }
        $optionList = $this->actionsBySeat[$seat];
        if(in_array($key, $optionList)){
            throw new InvalidActionException("Option already exists");
        }
        $optionList[] = $key;
        $this->actionsBySeat[$seat] = $optionList;
    }

    public function addOptionForAll($key) {
        foreach ($this->actionsBySeat as $seat => $item) {
            $this->addOption($seat, $key);
        }
    }

    public function playerActed($seat, $action) {
        if(!array_key_exists($seat, $this->actionsBySeat) || !in_array($action, $this->actionsBySeat[$seat])){
            throw new InvalidActionException("Seat or key not found");
        }
        $pos = array_search($action, $this->actionsBySeat[$seat]);
        unset($this->actionsBySeat[$seat][$pos]);
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
