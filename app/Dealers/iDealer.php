<?php

namespace App\Dealers;

use App\Models\Game;
use App\Models\GamePlayerMapping;

interface iDealer
{
    public function getGameType(): String;
    public function initWithGame(Game $game);
    public function joinAsPlayer(): GamePlayerMapping;
    public function addUserAction(String $actionKey, String $playerUuid);
    public function tick(String $playerUuid);
}
