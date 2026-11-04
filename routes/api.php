<?php

use App\Http\Controllers\Api\MovieController;
use Illuminate\Support\Facades\Route;

// Every route in this file is served under the /api prefix.

Route::get('/movies', [MovieController::class, 'index']);
