<?php

namespace App\Http\Controllers;

use App\Dealers\BaseDealer;
use App\Models\Player;
use App\Services\DealerService;
use App\Services\GameMappingService;
use App\Services\GameService;
use App\Services\PlayerService;
use Illuminate\Http\Request;

class HandController extends Controller
{
    private DealerService $dealerService;

    public function __construct(DealerService $dealerService) {
        $this->dealerService = $dealerService;
    }

    public function getStatusByUuid($playerUuid, $forceBroadcast = false) {
        $dealer = $this->getDealerByPlayerUuid($playerUuid, $forceBroadcast);
        return $dealer->getStatus($playerUuid);
    }

    private function getDealerByPlayerUuid($playerUuid, $forceBroadcast = false) : BaseDealer {
        $player = Player::with('game')->firstWhere(['uuid' => $playerUuid]);
        $result = $this->dealerService->buildDealer($player->game, $forceBroadcast);

        return $result;
    }

    public function postAction(Request $request) {
        $playerUuid = $request->get('playerUuid');
        $action = $request->get('action');
        $player = Player::with('game')->firstWhere(['uuid' => $playerUuid]);
        $hand = $player->game->hand;
        $this->dealerService->storeAction($player->game->id, $hand->id, [
            'key' => 'action',
            'action' => $action,
            'player_uuid' => $playerUuid
        ]);
        return $this->getStatusByUuid($playerUuid, true);
    }

    public function postOption(Request $request) {
        $playerUuid = $request->get('playerUuid');
        $option = $request->get('option');
        $player = Player::with('game')->firstWhere(['uuid' => $playerUuid]);
        $hand = $player->game->hand;
        $this->dealerService->storeAction($player->game->id, $hand->id, [
            'key' => 'option',
            'option' => $option,
            'player_uuid' => $playerUuid
        ]);
        return $this->getStatusByUuid($playerUuid, true);
    }
}
