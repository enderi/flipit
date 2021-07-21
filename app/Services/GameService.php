<?php

namespace App\Services;

use App\Lib\DeckLib\Deck;
use App\Models\Game;
use App\Models\Hand;
use App\Models\Invitation;
use Carbon\Carbon;
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
            'code' => random_int(100, 999), //Uuid::uuid4(),
            'expires_at' => Carbon::now()->addHour()
        ]);
        $game->invitation()->save($invitation);

        return $game;
    }
}
