<?php

namespace App\Services;

use App\Dealers\BaseDealer;
use App\Dealers\Exceptions\NewHandRequestedException;
use App\DomainObjects\Defaults;
use App\Events\GameStateChanged;
use App\Models\Action;
use App\Models\Game;
use App\Models\Player;
use Ramsey\Uuid\Uuid;

class DealerService {

    public function storeAction($gameId, $handId, $data) {
        $action = new Action();
        $action->fill([
            'game_id' => $gameId,
            'hand_id' => $handId,
            'data' => $data,
            'uuid' => Uuid::uuid4()
        ]);
        $action->save();
    }

    public function buildDealer(Game $game, $forceBroadcast = false) : BaseDealer
    {
        $currHand = $game->hand;
        $actions = $currHand->actions;
        $players = $game->players;
        $dealer = new Defaults::$gameInfo[$game->game_type]['dealer'];
        $dealer->initWithHand($players, $actions, $currHand->deck);
        try{
            $dealer->refreshState();
        } catch (NewHandRequestedException $newHandRequestedException) {
            // todo: prettify this routine
            $hand = $dealer->initializeHand();
            $createdHand = $game->hands()->create($hand);
            $game->hand_id = $createdHand->id;
            $game->save();
            $game->refresh();
            $currHand = $game->hand;
            $actions = $currHand->actions;
            $players = $game->players;
            $dealer = new Defaults::$gameInfo[$game->game_type]['dealer'];
            $dealer->initWithHand($players, $actions, $currHand->deck);
            $dealer->refreshState();
        }
        $actionsToSave = $dealer->getActionsToSave();
        $allActions = [];
        foreach($actionsToSave as $act) {
            $action = [
                'uuid' => Uuid::uuid4(),
                'game_id' => $game->id,
                'hand_id' => $currHand->id,
                'data' => $act
            ];
            $allActions[] = $action;
        }
        if(sizeof($allActions) > 0) {
            $currHand->actions()->createMany($allActions);
        }

        if(sizeof($allActions) > 0 || $forceBroadcast) {
            $this->broadcastStatus($players, $dealer);
        }
        return $dealer;
    }

    private function broadcastStatus($players, BaseDealer $dealer) {
        foreach($players as $player) {
            GameStateChanged::dispatch($player, [
                'action' => 'new-status',
                'status' =>  $dealer->getStatus($player->uuid)
            ]);
        };
    }
}
