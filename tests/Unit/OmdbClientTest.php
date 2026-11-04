<?php

namespace Tests\Unit;

use App\Services\Omdb\OmdbClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OmdbClientTest extends TestCase
{
    private const BASE_URL = 'https://www.omdbapi.com/';

    private function client(): OmdbClient
    {
        return new OmdbClient(self::BASE_URL, 'test-key');
    }

    private function fakeSearch(array $items = [], string $total = '0'): void
    {
        Http::fake([
            self::BASE_URL.'*' => Http::response([
                'Search' => $items,
                'totalResults' => $total,
                'Response' => 'True',
            ]),
        ]);
    }

    public function test_sends_key_query_and_page_to_omdb(): void
    {
        $this->fakeSearch();

        $this->client()->search('batman', 2);

        Http::assertSent(fn (Request $request) => $request->url() === self::BASE_URL.'?apikey=test-key&s=batman&page=2');
    }

    public function test_search_returns_normalized_items_and_total(): void
    {
        $this->fakeSearch([
            [
                'Title' => 'Batman Begins',
                'Year' => '2005',
                'imdbID' => 'tt0372784',
                'Type' => 'movie',
                'Poster' => 'https://example.com/begins.jpg',
            ],
        ], '412');

        $result = $this->client()->search('batman');

        $this->assertSame(412, $result['total']);
        $this->assertSame([
            [
                'imdb_id' => 'tt0372784',
                'title' => 'Batman Begins',
                'year' => '2005',
                'type' => 'movie',
                'poster' => 'https://example.com/begins.jpg',
            ],
        ], $result['items']);
    }

    public function test_missing_poster_becomes_null(): void
    {
        $this->fakeSearch([
            ['Title' => 'No Poster', 'Year' => '1999', 'imdbID' => 'tt0000001', 'Type' => 'movie', 'Poster' => 'N/A'],
        ], '1');

        $result = $this->client()->search('no poster');

        $this->assertNull($result['items'][0]['poster']);
    }
}
