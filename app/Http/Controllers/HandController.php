<?php

namespace App\Http\Controllers;

use ActionService;
use App\Dealers\OmahaFlip\OmahaFlipDealer;
use App\Dealers\TexasFlip\TexasFlipDealer;
use App\Dealers\TrickGame\LastTrickDealer;
use App\Models\Game;
use App\Models\GamePlayerMapping;
use Illuminate\Http\Request;

class HandController extends Controller
{

    public function getStatus(Request $request) {

        $playerUuid = $request->get('playerUuid');
        $dealer = $this->buildDealer($request->get('gameUuid'));
        return $dealer->tick($playerUuid);
    }

    public function tick($uuid){
        $mapping = GamePlayerMapping::firstWhere('uuid', $uuid);
        $gameUuid = $mapping->game->uuid;
        $playerUuid = $mapping->player->uuid;
        $dealer = $this->buildDealer($gameUuid);
        return $dealer->tick($playerUuid);
    }

    public function getStatusByUuid($uuid) {
        $mapping = GamePlayerMapping::firstWhere('uuid', $uuid);
        $gameUuid = $mapping->game->uuid;
        $playerUuid = $mapping->player->uuid;
        $dealer = $this->buildDealer($gameUuid);
        return $dealer->tick($playerUuid);
    }

    public function postAction(Request $request) {
        $mappingUuid = $request->get('uuid');
        $mapping = GamePlayerMapping::firstWhere('uuid', $mappingUuid);        
        $action = $request->get('action');
        $gameUuid = $mapping->game->uuid;
        $playerUuid = $mapping->player->uuid;
        $dealer = $this->buildDealer($gameUuid);
        $dealer->addUserAction($action, $playerUuid);
        return $dealer->tick($playerUuid);
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
        } else if ($game->game_type == LastTrickDealer::LAST_TRICK) {
            $dealer = LastTrickDealer::of($game);
        }
        return $dealer;
    }
}
