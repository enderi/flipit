<?php

namespace App\Http\Controllers;

use App\Dealers\OmahaDealer;
use App\Models\Action;
use App\Models\Game;
use App\Models\Hand;
use App\Models\Player;
use Illuminate\Http\Request;

class HandController extends Controller
{
    public function getStatus(Request $request) {
        $gameUuid = $request->get('gameUuid');
        $handUuid = $request->get('handUuid');
        $playerUuid = $request->get('playerUuid');

        // todo: keep active games in cache
        $game = Game::firstWhere('uuid', $gameUuid);
        $players = Player::where('game_id', $game->id)->get();
        $hand = Hand::firstWhere('uuid', $handUuid);
        if($hand != null) {
            $actions = Action::where('hand_id', $hand->id)->get();
        } else {
            $actions = [];
        }
        $dealer = new OmahaDealer($players, $actions);
        return [
            'actions' => $dealer->getStatus($playerUuid),
            'hand' => $hand != null ? $hand->uuid : null,
            'players' => $players,
            'game' => $game
        ];

        // return [
        //     'game' => $game,
        //     'hand' => $hand,
        //     'players' => $players,
        //     'actions' => $actions
        // ];
    }
}
