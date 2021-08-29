<?php

namespace App\DomainObjects;

class HandRank
{
    public $name;
    public $details;
    public $value;
    public $cards;

    public function __construct($name, $details, $cards)
    {
        $this->name = $name;
        $this->details = $details;
        $this->cards = $cards;
    }
    public function setName($name) {
        $this->setName($name);
    }

    public function setDetails($details){
        $this->details = $details;
    }
    public function setValue($val) {
        $this->value = $val;
    }
}
