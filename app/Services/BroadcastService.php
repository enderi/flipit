<?php

use App\Events\GameStateChanged;
use App\Services;

class BroadcastService {
    public function broadcast($game, $message) {
        GameStateChanged::dispatch($game, $message);
    }
}
