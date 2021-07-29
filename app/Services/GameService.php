<?php

namespace App\Services;

use App\Lib\DeckLib\Deck;
use App\Models\Action;
use App\Models\Game;
use App\Models\GamePlayerMapping;
use App\Models\Hand;
use App\Models\Invitation;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class GameService {
    public function createGame() {
        $game = Game::create([
            'uuid' => Uuid::uuid4(),
            'game_type' => 'omaha-flip',
            'min_seats' => 2,
            'max_seats' => 2,
            'information' => array()]);
        $invitation = new Invitation([
            'code' => Uuid::uuid4(),
            'expires_at' => Carbon::now()->addHour()
        ]);
        $game->invitation()->save($invitation);

        return $game;
    }

    public function newGame($gameType) {
        $game = Game::create([
            'uuid' => Uuid::uuid4(),
            'game_type' => $gameType,
            'min_seats' => 2,
            'max_seats' => 2,
            'information' => array()]);
        $invitation = new Invitation([
            'code' => Uuid::uuid4(),
            'expires_at' => Carbon::now()->addHour()
        ]);
        $game->invitation()->save($invitation);
        return $game;
    }

    public function joinGame($game) {
        $player = Player::create([
            'uuid' => Uuid::uuid4(),
            'game_id' => $game['id'],
            'seat_number' => $game->players->count() + 1
        ]);

        Action::create([
            'game_id' => $game->id,
            'uuid' => Uuid::uuid4(),
            'data' => [
                'playerUuid' => $player->uuid,
                'key' => 'player_joined'
            ]
        ]);
        return GamePlayerMapping::create(
            [
                'uuid' => Uuid::uuid4(),
                'game_id' => $game->id,
                'player_id' => $player->id
            ]);
    }
}
