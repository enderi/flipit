<?php

namespace App\Dealers\PokerGames\Traits;

use App\Events\GameStateChanged;

trait BroadcastStatus {
    public function broadcastStatus(): void
    {
        $this->game->mappings->each(function ($mapping) {
            GameStateChanged::dispatch($mapping, [
                'action' => 'new-status',
                'status' => $this->getStatus($mapping->player->uuid)
            ]);
        });
    }
}
