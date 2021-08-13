<?php

use App\Dealers\TexasFlip\Combinations;
use App\Http\Controllers\GameController;
use App\Lib\DeckLib\Deck;
use App\Models\Game;
use App\Models\Hand;
use App\Services\DealerService;
use App\Services\HoldemSolver;
use App\Services\Lookup;
use App\Services\Pokerank;
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

Route::get('/join/{code}', [GameController::class, 'joinWithCode'])->name('join-with-code');

Route::post('/join', [GameController::class, 'join'])->name('join-with-uuid');

Route::post('/flip', [GameController::class, 'create'])->name('game-create');

Route::get('/flip/{uuid}', [GameController::class, 'show'])->name('game-show');

Route::post('/flip/exit', [GameController::class, 'exitGame'])->name('exit-game');

Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');


Route::get('test', function(){
    $dealerService = new HoldemSolver();
    $deck = Deck::of("QhKh6cQc3s6s9cJc2c8hKc8d3hTh4c7d5c4h6d7s3c9hAd2hTsKd5d7c2sQdAhAsTd8cJsTc7h8s9dJh5s2dJdQs5h4s3d9s4d6hAcKs");
    for($i=0; $i<1; $i++) {
        //$deck = new Deck();
        //$deck->initialize();
        //$deck->shuffle();
        echo $deck->toString() . '<br>';
        $hand1 = collect([]);
        $hand2 = collect([]);
        $start = microtime(true);

        $hand1->push($deck->draw());
        $hand2->push($deck->draw());
        $hand1->push($deck->draw());
        $hand2->push($deck->draw());
        /*$hand1->push($deck->draw());
        $hand2->push($deck->draw());
        $hand1->push($deck->draw());
        $hand2->push($deck->draw());*/

        $table = collect($deck->draw(3));
        echo 'Hand 1: ' . collect($hand1)->map(function ($c) {
            return $c->toString();
        }) . "<br>";
    echo 'Hand 2: ' . collect($hand2)->map(function ($c) {
            return $c->toString();
        }) . "<br>";
    echo 'Flop: ' . collect($table)->map(function ($c) {
            return $c->toString();
        }) . "<br>";
        $result = $dealerService->calc($deck->getCards(), $table, $hand1, $hand2);        
        $winsFor1 = $result['1'];
        $winsFor2 = $result['2'];
        $ties = $result['0'];

        echo '1 win: ' . $winsFor1 . ', 2 win: ' . $winsFor2 . ', tied: ' . $ties;
        $time_elapsed_secs = microtime(true) - $start;
        echo 'time: ' . $time_elapsed_secs . '<br>';
        echo '------------------------------------<br>';
    }
});
