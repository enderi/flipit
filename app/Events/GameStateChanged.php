<?php

namespace App\Events;

use App\Models\Action;
use App\Models\Game;
use App\Models\GamePlayerMapping;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class GameStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private $gamePlayerMapping;
    public $action;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(GamePlayerMapping $gamePlayerMapping, $action)
    {
        $this->gamePlayerMapping = $gamePlayerMapping;
        $this->action = $action;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('game.' . $this->gamePlayerMapping->uuid);
    }
}
