<?php

namespace App\Http\Controllers;

use ActionService;
use App\Dealers\OmahaFlip\OmahaFlipDealer;
use App\Dealers\TexasFlip\TexasFlipDealer;
use App\Dealers\TrickGame\LastTrickDealer;
use App\Models\Game;
use App\Models\GamePlayerMapping;
use App\Services\DealerService;
use Illuminate\Http\Request;

class HandController extends Controller
{
    public function getStatus(Request $request, DealerService $dealerService) {
        $playerUuid = $request->get('playerUuid');
        $dealer = $dealerService->getDealerForUuid($request->get('gameUuid'));
        return $dealer->tick($playerUuid);
    }

    public function tick($uuid, DealerService $dealerService){
        $mapping = GamePlayerMapping::firstWhere('uuid', $uuid);
        $gameUuid = $mapping->game->uuid;
        $playerUuid = $mapping->player->uuid;
        $dealer = $dealerService->getDealerForUuid($gameUuid);
        return $dealer->tick($playerUuid);
    }

    public function getStatusByUuid($uuid, DealerService $dealerService) {
        $mapping = GamePlayerMapping::firstWhere('uuid', $uuid);
        $gameUuid = $mapping->game->uuid;
        $playerUuid = $mapping->player->uuid;
        $dealer = $dealerService->getDealerForUuid($gameUuid);
        return $dealer->tick($playerUuid);
    }

    public function postAction(Request $request, DealerService  $dealerService) {
        $mappingUuid = $request->get('uuid');
        $mapping = GamePlayerMapping::firstWhere('uuid', $mappingUuid);
        $action = $request->get('action');
        $gameUuid = $mapping->game->uuid;
        $playerUuid = $mapping->player->uuid;
        $dealer = $dealerService->getDealerForUuid($gameUuid);
        $dealer->addUserAction($action, $playerUuid);
        return $dealer->tick($playerUuid);
    }

    public function newHand(Request $request, DealerService $dealerService){
        $playerUuid = $request->get('playerUuid');
        $dealer = $dealerService->getDealerForUuid($request->get('gameUuid'));
        $dealer->requestNewHand($playerUuid);

        $dealer = $dealerService->getDealerForUuid($request->get('gameUuid'));
        $dealer->tick($playerUuid);
    }
}
