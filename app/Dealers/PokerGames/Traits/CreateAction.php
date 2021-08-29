<?php

namespace App\Dealers\PokerGames\Traits;

use App\Models\Action;
use Ramsey\Uuid\Uuid;

trait CreateAction
{
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
