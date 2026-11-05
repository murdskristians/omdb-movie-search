<?php

use App\Http\Controllers\Api\HistoryController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app');

// History lives in the session, which only the web middleware group starts. The URL still
// sits under /api so the frontend talks to one base address.
Route::prefix('api')->group(function () {
    Route::get('/history', [HistoryController::class, 'index']);
    Route::delete('/history', [HistoryController::class, 'destroy']);
});
