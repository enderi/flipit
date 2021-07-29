<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\HandController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/hand/tick', [HandController::class, 'tick']);

Route::post('/hand-status', [HandController::class, 'getStatus']);

Route::post('/hand-status/revealed', [HandController::class, 'getRevealedCards']);

Route::post('/hand-status/action', [HandController::class, 'postAction']);

Route::post('/hand-status/new', [HandController::class, 'newHand']);

Route::get('/game/stats/{gameUuid}', [GameController::class, 'getStats'])->name('stats');