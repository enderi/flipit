<?php

namespace App\Lib\DeckLib;

class HandRank
{
    public $name;
    public $details;

    public function __construct($name, $details)
    {
        $this->name = $name;
        $this->details = $details;
    }
    public function setName($name) {
        $this->setName($name);
    }

    public function setDetails($details){
        $this->details = $details;
    }
}
