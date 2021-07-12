<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\Game;
use App\Models\Hand;
use App\Models\Invitation;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Ramsey\Uuid\Uuid;

class FlipController extends Controller
{
    public function create(Request $request)
    {
        $invitationCode = $request->get('invitationCode');

        $invitation = Invitation::where('code', $invitationCode)
        ->where('expired_at', '>=', Carbon::now())
        ->first();

        if($invitation == null) {
            $game = Game::create([
                'uuid' => Uuid::uuid4(),
                'game_type' => 'omaha']);
            $invitation = Invitation::create([
                'code' => $invitationCode,
                'expires_at' => Carbon::now()->addHour(),
                'game_id' => $game->id
            ]);
        } else {
            $game = Game::firstWhere('id', $invitation->game_id);
        }

        $player = Player::create([
            'uuid' => Uuid::uuid4(),
            'game_id' => $game['id']
        ]);

        return Redirect::route('flip-show', ['gameUuid' => $game->uuid, 'playerUuid' => $player->uuid]);
    }
    public function show($playerUuid, $gameUuid){
        $game = Game::firstWhere('uuid', $gameUuid);
        $player = Player::firstWhere('uuid', $playerUuid);

        return Inertia::render(
            'Flip',
            [
                'params' => [
                    'game' => $game,
                    'player' => $player,
                    'players' => Player::where('game_id', $game['id'])->get(),
                    'actions' => []
                ]
            ]
        );
    }
}
