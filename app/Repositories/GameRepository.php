<?php

namespace App\Repositories;

use App\DomainObjects\GameAggregate;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class GameRepository
{
    public function persistGameAggregate(GameAggregate $gameAggregate) {
        DB::beginTransaction();
        $gameToUpdate = $gameAggregate->toArray();
        $existingGame = Game::firstOrCreate(['uuid' => $gameToUpdate['uuid']], $gameToUpdate);
        $players = $gameAggregate->getPlayers();

        foreach($players as $player) {
            if(is_array($player) && !key_exists('id', $player)){
                $existingGame->players()->create($player);
            }
        }
        $handToInsert = $gameAggregate->getInitialHand();
        if($handToInsert != null) {
            $handToInsert['game_id'] = $existingGame->id;
            $hand = $existingGame->hand()->create($handToInsert);
            $existingGame->hand_id = $hand->id;
            $existingGame->save();
        }
        DB::commit();
        $existingGame->refresh();
        return $existingGame;
    }
}
