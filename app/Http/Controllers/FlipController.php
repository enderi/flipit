<?php

namespace App\Http\Controllers;

use App\Dealers\OmahaFlip\OmahaFlipDealer;
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
    public function create()
    {
        $dealer = new OmahaFlipDealer();
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
        $dealer = OmahaFlipDealer::of($game);
        $mapping = $dealer->joinAsPlayer();
        $invitation->expires_at = Carbon::now()->addSecond(-1);
        $invitation->update();

        return Redirect::route('flip-show', ['uuid' => $mapping->uuid]);
    }
}
