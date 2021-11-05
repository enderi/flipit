<?php

namespace App\Http\Controllers;

use App\Dealers\PokerGames\CrazyPineapple\CrazyPineappleDealer;
use App\Dealers\PokerGames\OmahaFlip\OmahaFlipDealer;
use App\Dealers\PokerGames\FourStreetGames\TexasFlipDealer;
use App\Dealers\TrickGame\LastTrickDealer;
use App\Services\GameService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class BrushController extends Controller
{
    public function create(
        Request     $request,
        GameService $gameService)
    {
        $gameType = $request->get('gameType');
        $game = $gameService->newGame($gameType);

        $result = [
            'gameUuid' => $game->getUuid(),
            'playerUuid' => $game->getUuidOfRequestedPlayer()];
        if($game->areSeatsFilled()) {
            return Redirect::route('game-show', $result);
        }
        return Redirect::route('waiting', $result);
    }

    public function waiting($gameUuid, $playerUuid) {
        return Inertia::render('WaitingRoom/Waiting', [
            'gameUuid' => $gameUuid,
            'playerUuid' => $playerUuid,
            'invitationUrl' => route('join-with-uuid', ['gameUuid' => $gameUuid])]);
    }

    public function join(
        $gameUuid,
        GameService $gameService)
    {
        try {
            $game = $gameService->joinWithGameUuid($gameUuid);
        } catch (ModelNotFoundException $exception) {
            return Redirect::route('join', ['error' => 'Not found']);
        }
        return Redirect::route('game-show', [
            'gameUuid' => $gameUuid,
            'playerUuid' => $game->getUuidOfRequestedPlayer()
        ]);
    }

    public function playAlone(
        $gameUuid,
        $playerUuid,
        GameService $gameService) {
        try {
            $game = $gameService->playAlone($gameUuid);
        } catch (ModelNotFoundException $exception) {
            return Redirect::route('join', ['error' => 'Not found']);
        }
        return Redirect::route('game-show', [
            'gameUuid' => $gameUuid,
            'playerUuid' => $playerUuid
        ]);
    }

    public function show($uuid, GameService $gameService)
    {
        dd('hep');
        $common = $gameService->buildGameInfoForMappingUuid($uuid);
        if (in_array($common['gameType'], [OmahaFlipDealer::OMAHA_FLIP, TexasFlipDealer::TEXAS_FLIP])) {
            return Inertia::render('GameTypes/HoldemFlips/HoldemFlip', ['params' => $common]);
        }
        if (in_array($common['gameType'], [CrazyPineappleDealer::CRAZY_PINEAPPLE])) {
            return Inertia::render('GameTypes/HoldemFlips/CrazyPineappleFlip', ['params' => $common]);
        }
        if ($common['gameType'] == LastTrickDealer::LAST_TRICK) {
            return Inertia::render('LastTrick', ['params' => $common]);
        }
    }

}
