<?php

namespace App\Services;

use App\Dealers\DealerBase;
use App\Dealers\PokerGames\CrazyPineapple\CrazyPineappleDealer;
use App\Dealers\PokerGames\OmahaFlip\OmahaFlipDealer;
use App\Dealers\PokerGames\TexasFlip\TexasFlipDealer;
use App\Dealers\TrickGame\LastTrickDealer;
use App\Models\Game;

class DealerService {
    public function getDealer($game) :DealerBase {
        if ($game->game_type == TexasFlipDealer::TEXAS_FLIP) {
            $dealer = TexasFlipDealer::of($game);
        } else if ($game->game_type == OmahaFlipDealer::OMAHA_FLIP) {
            $dealer = OmahaFlipDealer::of($game);
        } elseif ($game->game_type == CrazyPineappleDealer::CRAZY_PINEAPPLE) {
            $dealer = CrazyPineappleDealer::of($game);
        } else if ($game->game_type == LastTrickDealer::LAST_TRICK) {
            $dealer = LastTrickDealer::of($game);
        }
        return $dealer;
    }

    public function getDealerForUuid($gameUuid) :DealerBase {
        $game = Game::firstWhere('uuid', $gameUuid);
        return $this->getDealer($game);
    }
}
