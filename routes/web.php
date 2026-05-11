<?php

declare(strict_types=1);

use App\Http\Controllers\Api\JokesController as ApiJokesController;
use App\Http\Controllers\Api\TrackController;
use App\Http\Controllers\JokesController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [JokesController::class, 'index'])->name('home');

Route::get('api/jokes', [ApiJokesController::class, 'index'])->name('api.jokes.index');

Route::post('api/track', [TrackController::class, 'store'])
    ->middleware('throttle:track')
    ->name('api.track.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('sites', SiteController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('sites/{site}/stats', [StatsController::class, 'show'])
        ->name('sites.stats.show');
    Route::get('sites/{site}/stats/hourly', [StatsController::class, 'hourly'])
        ->name('sites.stats.hourly');
    Route::get('sites/{site}/stats/cities', [StatsController::class, 'cities'])
        ->name('sites.stats.cities');
});
