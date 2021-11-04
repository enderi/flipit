<?php

namespace App\Http\Controllers;

use App\Dealers\PokerGames\CrazyPineapple\CrazyPineappleDealer;
use App\Dealers\PokerGames\OmahaFlip\OmahaFlipDealer;
use App\Dealers\PokerGames\TexasFlip\TexasFlipDealer;
use App\Dealers\TrickGame\LastTrickDealer;
use App\Events\GameStateChanged;
use App\Models\Game;
use App\Services\GameService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class GameController extends Controller
{
    private GameService $gameService;

    public function __construct(GameService $gameService){
        $this->gameService = $gameService;
    }
    public function show($gameUuid, $playerUuid){
        $game = $this->gameService->buildGameInfoForMappingUuid($gameUuid, $playerUuid);
        if (!$game->areSeatsFilled()){
            return Inertia::render('WaitingRoom/Waiting', [
                'gameUuid' => $gameUuid,
                'invitationUrl' => route('join-with-uuid', ['gameUuid' => $gameUuid])]);
        }
        if($game->isOfType('OMAHA-FLIP') || $game->isOfType('TEXAS-FLIP')){
            return Inertia::render('GameTypes/HoldemFlips/HoldemFlip', [
                'gameUuid' => $gameUuid,
                'playerUuid' => $playerUuid,
                'gameType' => $game->getType()]);
        }
        if($game->isOfType('CRAZY_PINEAPPLE')){
            return Inertia::render('GameTypes/HoldemFlips/CrazyPineappleFlip', ['params' => $game]);
        }
        if($game->isOfType(LastTrickDealer::LAST_TRICK)) {
            return Inertia::render('LastTrick', ['params' => $game]);
        }
    }

    public function join(
        Request $request,
        GameService $gameService) {
        $code = $request->get('code');
        try {
            $game = $gameService->joinWithGameUuid($code);
        }catch (ModelNotFoundException $exception){
            return Redirect::route('join', ['error' => 'Not found']);
        }
        return Redirect::route('game-show', ['uuid' => $game->getUuid()]);
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
