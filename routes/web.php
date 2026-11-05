<?php

use App\Http\Controllers\Api\HistoryController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app');

// History lives in the session, which only the web middleware group starts. The URL still
// sits under /api so the frontend talks to one base address. These routes have a request limit
// of their own, so history requests do not use up the limit of the search routes.
Route::prefix('api')->middleware('throttle:60,1,history')->group(function () {
    Route::get('/history', [HistoryController::class, 'index']);
    Route::delete('/history', [HistoryController::class, 'destroy']);
});
