<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SearchHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => new SearchHistory($request->session())->all()]);
    }

    public function destroy(Request $request): Response
    {
        new SearchHistory($request->session())->clear();

        return response()->noContent();
    }
}
