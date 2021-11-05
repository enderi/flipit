<?php

use App\Events\PlayerJoined;
use App\Http\Controllers\BrushController;
use App\Http\Controllers\GameController;
use App\Models\Game;
use App\Models\Hand;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Home', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'handCount' => Hand::get()->count(),
        'gameCount' => Game::get()->count(),
    ]);
})->name('home');
Route::post('/game', [BrushController::class, 'create'])->name('game-create');
Route::get('/game/waiting/{gameUuid}/{playerUuid}', [BrushController::class, 'waiting'])->name('waiting');

Route::get('/game/{gameUuid}/player/{playerUuid}', [GameController::class, 'show'])->name('game-show');

Route::post('/game/{gameUuid}/player/{playerUuid}/play-alone', [BrushController::class, 'playAlone'])->name('join-alone');

Route::get('/join', function (\Illuminate\Http\Request $request) {
    $errors = $request->get('error');
    return Inertia::render('Join', ['error'=>$errors]);
})->name('join');


Route::get('/join/{code}', function (\Illuminate\Http\Request $request, $code) {
    PlayerJoined::dispatch($code, 'IN_THE_LOBBY');
    $errors = $request->get('error');
    return Inertia::render('Join', ['error'=>$errors, 'code' => $code]);
})->name('show-join');

Route::post('/join/{gameUuid}', [BrushController::class, 'join'])->name('join-with-uuid');


Route::get('/game/{gameUuid}/{playerUuid}', [GameController::class, 'show'])->name('game-show');

Route::get('/flip/{gameUuid}/{playerUuid}', [GameController::class, 'show'])->name('game-show');
Route::post('/flip/exit', [GameController::class, 'exitGame'])->name('exit-game');


Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');
