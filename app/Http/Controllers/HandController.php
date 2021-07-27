<?php

namespace App\Http\Controllers;

use App\Dealers\OmahaFlip\OmahaFlipDealer;
use App\Dealers\TexasFlip\TexasFlipDealer;
use App\Models\Game;
use Illuminate\Http\Request;

class HandController extends Controller
{

    public function getStatus(Request $request) {
        $playerUuid = $request->get('playerUuid');
        $dealer = $this->buildDealer($request->get('gameUuid'));
        return $dealer->tick($playerUuid);
    }

    public function postAction(Request $request) {
        $playerUuid = $request->get('playerUuid');
        $action = $request->get('action');
        $actionUuid = $request->get('actionUuid');
        $dealer = $this->buildDealer($request->get('gameUuid'));
        $dealer->addUserAction($action, $actionUuid, $playerUuid);

        $dealer = $this->buildDealer($request->get('gameUuid'));
        $dealer->tick($playerUuid);
    }

    public function newHand(Request $request){
        $playerUuid = $request->get('playerUuid');
        $dealer = $this->buildDealer($request->get('gameUuid'));

        $dealer->requestNewHand($playerUuid);

        $dealer = $this->buildDealer($request->get('gameUuid'));
        $dealer->tick($playerUuid);
    }

    private function buildDealer($gameUuid) {
        $game = Game::firstWhere('uuid', $gameUuid);
        return $this->getDealer($game);
    }

    private function getDealer($game)
    {
        if ($game->game_type == TexasFlipDealer::TEXAS_FLIP) {
            $dealer = TexasFlipDealer::of($game);
        } else if ($game->game_type == OmahaFlipDealer::OMAHA_FLIP) {
            $dealer = OmahaFlipDealer::of($game);
        }
        return $dealer;
    }
}
