<?php

namespace App\Http\Controllers;

use App\Dealers\OmahaFlip\OmahaFlipDealer;
use App\Dealers\TexasFlip\TexasFlipDealer;
use App\Models\Game;
use App\Models\GamePlayerMapping;
use App\Models\Invitation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class FlipController extends Controller
{
    public function create(Request $request)
    {
        $gameType = $request->get('gameType');
        $dealer = null;
        if($gameType == 'OMAHA-FLIP') {
            $dealer = new OmahaFlipDealer();
        }
        if($gameType == 'TEXAS-FLIP') {
            $dealer = new TexasFlipDealer();
        }
        $dealer->newGame();
        $mapping = $dealer->joinAsPlayer();
        return Redirect::route('flip-show', ['uuid' => $mapping->uuid]);
    }

    public function show($uuid){
        $mapping = GamePlayerMapping::firstWhere('uuid', $uuid);

        $game = $mapping->game;
        $player = $mapping->player;

        $invitation = Invitation::firstWhere('game_id', $game->id);

        return Inertia::render(
            'Flip',
            [
                'params' => [
                    'game' => $game,
                    'playerUuid' => $player->uuid,
                    'invitationCode' => $invitation->code,
                    'invitationUrl' => route('join-with-code', ['code' => $invitation->code])
                ]
            ]
        );
    }

    public function join(Request $request) {
        $code = $request->get('code');
        return $this->joinWithCode($code);
    }

    public function joinWithCode($inviteUuid) {
        $invitation = null;

        try {
            $invitation = Invitation::where('code', $inviteUuid)->where('expires_at', '>=', Carbon::now())->firstOrFail();
        }catch (ModelNotFoundException $exception){
            return Redirect::route('join', ['error' => 'Not found']);
        }
        $game = Game::find($invitation->game_id);
        $dealer = $this->getDealer($game);
        $mapping = $dealer->joinAsPlayer();
        $invitation->expires_at = Carbon::now()->addSecond(-1);
        $invitation->update();

        return Redirect::route('flip-show', ['uuid' => $mapping->uuid]);
    }

    /**
     * @param $game
     * @return OmahaFlipDealer|TexasFlipDealer
     */
    private function getDealer($game)
    {
        if ($game->game_type == TexasFlipDealer::TEXAS_FLIP) {
            $dealer = TexasFlipDealer::of($game);
        } else if ($game->game_type == OmahaFlipDealer::OMAHA_FLIP) {
            $dealer = OmahaFlipDealer::of($game);
        }
        return $dealer;
    }
}
