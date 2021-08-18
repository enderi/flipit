<?php

namespace App\Services;

use App\Models\GamePlayerMapping;
use App\Models\Player;
use Ramsey\Uuid\Uuid;

class PlayerService {
    public function joinGame($game) :GamePlayerMapping {
        $player = Player::create([
            'uuid' => Uuid::uuid4(),
            'game_id' => $game['id'],
            'seat_number' => $game->players->count() + 1,
            'ready' => 1
        ]);
        $mapping = GamePlayerMapping::create(
            [
                'uuid' => Uuid::uuid4(),
                'game_id' => $game->id,
                'player_id' => $player->id
            ]);
        return $mapping;
    }

}
