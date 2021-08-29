<?php

namespace App\Dealers;

use App\Dealers\PokerGames\Traits\BroadcastStatus;
use App\Dealers\PokerGames\Traits\CreateAction;
use App\Models\Action;
use Ramsey\Uuid\Uuid;

abstract class DealerBase
{
    use CreateAction;
    use BroadcastStatus;

    protected $game;
    protected $currentHand;
    public abstract function getGameType(): String;
    public abstract function addUserAction(String $actionKey, String $playerUuid);
    public abstract function addUserOption(String $actionKey, String $playerUuid);
    public abstract function tick(String $playerUuid, $forceBroadcast = false): array;

    protected abstract function getStatus($playerUuid): array;
    protected abstract function refreshState();

    public function initWithGame($game)
    {
        $this->game = $game;
        $this->currentHand = $game->hand;
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
