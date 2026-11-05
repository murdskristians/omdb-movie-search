<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchMovieRequest;
use App\Http\Resources\MovieDetailsResource;
use App\Http\Resources\MovieSummaryResource;
use App\Services\Omdb\OmdbClient;
use App\Services\SearchHistory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MovieController extends Controller
{
    public function index(SearchMovieRequest $request, OmdbClient $omdb): AnonymousResourceCollection
    {
        $page = $request->page();
        $result = $omdb->search($request->searchQuery(), $page);

        // Done here and not inside OmdbClient::search(), so answers served from the cache count too.
        // Only page 1 is a new search; the later pages are the visitor browsing the same one.
        if ($page === 1 && $result['items'] !== []) {
            new SearchHistory($request->session())->record($request->searchQuery());
        }

        return MovieSummaryResource::collection($result['items'])->additional([
            'meta' => [
                'page' => $page,
                'total' => $result['total'],
                'per_page' => OmdbClient::PAGE_SIZE,
                'last_page' => max(1, (int) ceil($result['total'] / OmdbClient::PAGE_SIZE)),
            ],
        ]);
    }

    public function show(string $imdbId, OmdbClient $omdb): MovieDetailsResource
    {
        return new MovieDetailsResource($omdb->find($imdbId));
    }
}
