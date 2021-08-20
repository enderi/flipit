<?php

namespace App\Dealers;

use App\Models\Game;

interface iDealer
{
    public function getGameType(): String;
    public function addUserAction(String $actionKey, String $playerUuid);
    public function tick(String $playerUuid);
}
