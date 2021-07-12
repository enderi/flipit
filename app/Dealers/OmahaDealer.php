<?php

namespace App\Dealers;

class OmahaDealer {
    private $players;
    private $actions;

    private $allowedActions;

    public function __construct($players, $actions) {
        $this->players = $players;
        $this->actions = $actions;
        $this->allowedActions = collect();
    }
    
    public function getStatus($playerUuid) {
        return [
            'actions' => $this->myOptions($playerUuid)
        ];
    }

    private function myOptions($playerUuid) {
        if ($this->actions == null || $this->actions->isEmpty()){
            $this->allowedActions->push(['action'=>'start']);
        }
        return $this->getAllowedActions();
    }

    private function getAllowedActions() {
        return $this->allowedActions;
    }

}