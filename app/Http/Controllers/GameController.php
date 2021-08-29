<?php

namespace App\Http\Controllers;

use App\Dealers\PokerGames\OmahaFlip\OmahaFlipDealer;
use App\Dealers\PokerGames\TexasFlip\TexasFlipDealer;
use App\Dealers\TrickGame\LastTrickDealer;
use App\Events\GameStateChanged;
use App\Models\Game;
use App\Models\GamePlayerMapping;
use App\Models\Invitation;
use App\Services\DealerService;
use App\Services\GameService;
use App\Services\PlayerService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Ramsey\Uuid\Uuid;

class GameController extends Controller
{
    public function create(
        Request $request,
        GameService $gameService,
        PlayerService $playerService,
        DealerService $dealerService)
    {
        $gameType = $request->get('gameType');
        $game = $gameService->newGame($gameType);
        $mapping = $playerService->joinGame($game);
        $dealer = $dealerService->getDealer($game);
        $dealer->tick($mapping->player->uuid);
        return Redirect::route('game-show', ['uuid' => $mapping->uuid]);
    }

    public function show($uuid){
        $mapping = GamePlayerMapping::firstWhere('uuid', $uuid);

        $game = $mapping->game;
        $player = $mapping->player;

        $invitation = Invitation::where('game_id', $game->id)
            ->where('expires_at', '>', Carbon::now())->first();
        $common = [
            'uuid' => $uuid,
            'game' => $game,
            'seatNumber' => $player->seat_number,
            'playerUuid' => $player->uuid];
        if($invitation != null) {
            $common['invitationCode'] = $invitation->code;
            $common['invitationUrl'] = route('show-join', ['code' => $invitation->code]);
        }
        if(in_array($game->game_type, [OmahaFlipDealer::OMAHA_FLIP, TexasFlipDealer::TEXAS_FLIP])){
            return Inertia::render('GameTypes/HoldemFlips/HoldemFlip', ['params' => $common]);
        }
        if($game->game_type == LastTrickDealer::LAST_TRICK) {
            return Inertia::render('LastTrick', ['params' => $common]);
        }
    }

    public function join(
        Request $request,
        DealerService $dealerService,
        PlayerService $playerService) {
        $code = $request->get('code');
        return $this->joinWithCode($code, $dealerService, $playerService);
    }

    public function showJoinWithCode($inviteUuid) {
        return Inertia::render(
            'Join',
            [
                'code' => $inviteUuid
            ]
            );
    }


    public function joinWithCode(
        $inviteUuid,
        DealerService $dealerService,
        PlayerService $playerService) {
        try {
            $invitation = Invitation::where('code', $inviteUuid)->where('expires_at', '>=', Carbon::now())->firstOrFail();
        }catch (ModelNotFoundException $exception){
            return Redirect::route('join', ['error' => 'Not found']);
        }
        $game = Game::find($invitation->game_id);
        $mapping = $playerService->joinGame($game);
        $dealer = $dealerService->getDealer($game);
        $dealer->tick($mapping->player->uuid, true);
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
}
