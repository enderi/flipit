<?php

use App\Http\Controllers\GameController;
use App\Models\Game;
use App\Models\Hand;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Ramsey\Uuid\Uuid;

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

Route::get('/join', function (\Illuminate\Http\Request $request) {
    $errors = $request->get('error');
    return Inertia::render('Join', ['error'=>$errors]);
})->name('join');


//Route::get('/join/{code}', [GameController::class, 'joinWithCode'])->name('join-with-code');

Route::get('/join/{code}', function (\Illuminate\Http\Request $request, $code) {
    $errors = $request->get('error');
    return Inertia::render('Join', ['error'=>$errors, 'code' => $code]);
})->name('show-join');


Route::post('/join', [GameController::class, 'join'])->name('join-with-uuid');

Route::post('/flip', [GameController::class, 'create'])->name('game-create');

Route::get('/flip/{uuid}', [GameController::class, 'show'])->name('game-show');

Route::post('/flip/exit', [GameController::class, 'exitGame'])->name('exit-game');

Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');
