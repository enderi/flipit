<?php

namespace App\Dealers;

use App\Events\GameStateChanged;
use App\Models\Action;
use App\Models\Game;
use App\Models\GamePlayerMapping;
use Ramsey\Uuid\Uuid;

abstract class DealerBase
{
    protected $game;
    protected $currentHand;
    public abstract function getGameType(): String;
    public abstract function joinAsPlayer(): GamePlayerMapping;
    public abstract function addUserAction(String $actionKey, String $playerUuid);
    public abstract function tick(String $playerUuid);

    protected abstract function getStatus($playerUuid): array;
    protected abstract function refreshState();

    public function initWithGame($game)
    {
        $this->game = $game;
        $this->currentHand = $game->getCurrentHand();
        return $this->refreshState();
    }

    public function requestNewHand($playerUuid)
    {
        $this->createAction(['hand_id' => $this->currentHand->id,
            'uuid' => Uuid::uuid4(),
            'data' => [
                'key' => 'REQUEST_NEW_HAND',
                'playerUuid' => $playerUuid
            ]
        ]);
    }

    protected function broadcastStatus(): void
    {
        $this->game->mappings->each(function ($mapping) {
            GameStateChanged::dispatch($mapping, [
                'action' => 'new-status',
                'status' => $this->getStatus($mapping->player->uuid)
            ]);
        });
    }

    protected function createAction($data)
    {
        $action = [
            'uuid' => Uuid::uuid4(),
            'game_id' => $this->game->id,
            'hand_id' => $this->currentHand->id,
            'data' => $data
        ];
        Action::create($action);
    }
}
