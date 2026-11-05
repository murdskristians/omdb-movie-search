<?php

use App\Http\Controllers\Api\MovieController;
use Illuminate\Support\Facades\Route;

// Every route in this file is served under the /api prefix.

// A successful search is added to the visitor's history, which lives in the session. Only
// the web group starts a session, so this route gets it on top of the api group.
Route::middleware('web')->get('/movies', [MovieController::class, 'index']);

// IMDb ids look like tt0372784: "tt" and 7 to 10 digits. Anything else does not match the route and
// gets a 404 without calling OMDb.
Route::get('/movies/{imdbId}', [MovieController::class, 'show'])->where('imdbId', 'tt\d{7,10}');
