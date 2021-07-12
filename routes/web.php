<?php

use App\Http\Controllers\FlipController;
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
    ]);
});

Route::get('/lets-do-it', function () {
    return Inertia::render('LetsDoIt', [
        'inviteCode' => random_int(100, 999),
        'inviteUuid' => Uuid::uuid4()
    ]);
})->name('lets-do-it');

Route::post('/flip', [FlipController::class, 'create'])->name('flip-create');

// todo: create table where one uuid maps to both table and player
Route::get('/flip/{playerUuid}/{gameUuid}', [FlipController::class, 'show'])->name('flip-show');


Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');
