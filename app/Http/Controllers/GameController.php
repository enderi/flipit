<?php

namespace App\Http\Controllers;

use App\Dealers\OmahaFlip\OmahaFlipDealer;
use App\Dealers\TexasFlip\TexasFlipDealer;
use App\Dealers\TrickGame\LastTrickDealer;
use App\Events\GameStateChanged;
use App\Lib\DeckLib\Deck;
use App\Models\Game;
use App\Models\GamePlayerMapping;
use App\Models\Hand;
use App\Models\Invitation;
use App\Services\GameService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Ramsey\Uuid\Uuid;

class GameController extends Controller
{
    public function create(Request $request, GameService $gameService)
    {
        $gameType = $request->get('gameType');
        $game = $gameService->newGame($gameType);
        $dealer = $this->getDealer($game);
        $mapping = $dealer->joinAsPlayer();
        return Redirect::route('game-show', ['uuid' => $mapping->uuid]);
    }

    public function show($uuid){
        $mapping = GamePlayerMapping::firstWhere('uuid', $uuid);

        $game = $mapping->game;
        $player = $mapping->player;

        $invitation = Invitation::firstWhere('game_id', $game->id);
        if(in_array($game->game_type, [OmahaFlipDealer::OMAHA_FLIP, TexasFlipDealer::TEXAS_FLIP])){
            return Inertia::render(
                'Flip',
                [
                    'params' => [
                        'uuid' => $uuid,
                        'game' => $game,
                        'seatNumber' => $player->seat_number,
                        'playerUuid' => $player->uuid,
                        'invitationCode' => $invitation->code,
                        'invitationUrl' => route('join-with-code', ['code' => $invitation->code])
                    ]
                ]
            );
        }
        if($game->game_type == LastTrickDealer::LAST_TRICK) {
            return Inertia::render(
                'LastTrick',
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
    }

    public function join(Request $request) {
        $code = $request->get('code');
        return $this->joinWithCode($code);
    }

    public function joinWithCode($inviteUuid) {
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

        return Redirect::route('game-show', ['uuid' => $mapping->uuid]);
    }

    public function exitGame(Request $request) {
        $gameUuid = $request->get('gameUuid');
        $game = Game::firstWhere('uuid', $gameUuid);

        GameStateChanged::dispatch($game, 'opponent-left');
        return Redirect::route('home');
    }

    public function getStats($gameUuid){
        $game = Game::firstWhere('uuid', $gameUuid);
        $hands = $game->hands;
        $pointsPerSet = [];
        foreach($hands as $hand){
            foreach($hand->result as $key=>$value){
                if(!array_key_exists($key, $pointsPerSet)){
                    $pointsPerSet[$key] = 0;
                }
                if($value['result'] == 'win'){
                    $pointsPerSet[$key] = $pointsPerSet[$key] + 1;
                }
            }
        }

        return [
            'winsBySeat' => $pointsPerSet
        ];
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
        } else if ($game->game_type == LastTrickDealer::LAST_TRICK) {
            $dealer = LastTrickDealer::of($game);
        }
        return $dealer;
    }
}
