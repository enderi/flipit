<?php

namespace App\Http\Controllers;

use App\Dealers\OmahaFlip\OmahaFlipDealer;
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
        $dealer = OmahaFlipDealer::of($game);
        return $dealer;
    }
}
