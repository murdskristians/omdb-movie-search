<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchMovieRequest;
use App\Http\Resources\MovieSummaryResource;
use App\Services\Omdb\OmdbClient;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MovieController extends Controller
{
    public function index(SearchMovieRequest $request, OmdbClient $omdb): AnonymousResourceCollection
    {
        $page = $request->page();
        $result = $omdb->search($request->searchQuery(), $page);

        return MovieSummaryResource::collection($result['items'])->additional([
            'meta' => [
                'page' => $page,
                'total' => $result['total'],
                'per_page' => OmdbClient::PAGE_SIZE,
                'last_page' => max(1, (int) ceil($result['total'] / OmdbClient::PAGE_SIZE)),
            ],
        ]);
    }
}
