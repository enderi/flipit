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

Route::get('/join', function (\Illuminate\Http\Request $request) {
    $errors = $request->get('error');
    return Inertia::render('Join', ['error'=>$errors]);
})->name('join');

Route::get('/join/{code}', [FlipController::class, 'joinWithCode'])->name('join-with-code');

Route::post('/join', [FlipController::class, 'join'])->name('join-with-uuid');

Route::post('/flip', [FlipController::class, 'create'])->name('flip-create');

Route::get('/flip/{uuid}', [FlipController::class, 'show'])->name('flip-show');


Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');
