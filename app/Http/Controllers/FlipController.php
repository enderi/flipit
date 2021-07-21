<?php

namespace App\Http\Controllers;

use App\Dealers\TexasFlip\TexasFlipDealer;
use App\Models\Game;
use App\Models\GamePlayerMapping;
use App\Models\Invitation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class FlipController extends Controller
{
    public function create()
    {
        $dealer = new TexasFlipDealer();
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
                    'invitationCode' => $invitation->code
                ]
            ]
        );
    }

    public function join($inviteUuid) {
        // todo: handle expired invitations
        $invitation = Invitation::where('code', $inviteUuid)->where('expires_at', '>=', Carbon::now())->first();

        $game = Game::find($invitation->game_id);
        $dealer = TexasFlipDealer::of($game);
        $mapping = $dealer->joinAsPlayer();
        $invitation->expires_at = Carbon::now()->addSecond(-1);
        $invitation->update();

        return Redirect::route('flip-show', ['uuid' => $mapping->uuid]);
    }
}
