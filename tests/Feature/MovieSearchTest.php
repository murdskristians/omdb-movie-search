<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MovieSearchTest extends TestCase
{
    private function fakeOmdbSearch(array $items, string $total): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Search' => $items,
                'totalResults' => $total,
                'Response' => 'True',
            ]),
        ]);
    }

    private function omdbMovie(string $id, string $title, string $poster = 'https://example.com/p.jpg'): array
    {
        return ['Title' => $title, 'Year' => '2005', 'imdbID' => $id, 'Type' => 'movie', 'Poster' => $poster];
    }

    public function test_returns_movies_and_paging_info(): void
    {
        $this->fakeOmdbSearch([
            $this->omdbMovie('tt0372784', 'Batman Begins'),
            $this->omdbMovie('tt1877830', 'The Batman'),
        ], '25');

        $this->getJson('/api/movies?q=batman&page=2')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    ['imdb_id' => 'tt0372784', 'title' => 'Batman Begins', 'year' => '2005', 'type' => 'movie', 'poster' => 'https://example.com/p.jpg'],
                    ['imdb_id' => 'tt1877830', 'title' => 'The Batman', 'year' => '2005', 'type' => 'movie', 'poster' => 'https://example.com/p.jpg'],
                ],
                'meta' => ['page' => 2, 'total' => 25, 'per_page' => 10, 'last_page' => 3],
            ]);
    }

    public function test_asks_omdb_for_the_requested_title_and_page(): void
    {
        $this->fakeOmdbSearch([], '0');

        $this->getJson('/api/movies?q=batman&page=3');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 's=batman&page=3'));
    }

    public function test_page_defaults_to_one(): void
    {
        $this->fakeOmdbSearch([], '0');

        $this->getJson('/api/movies?q=batman')->assertOk()->assertJsonPath('meta.page', 1);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'page=1'));
    }

    public function test_missing_poster_is_null(): void
    {
        $this->fakeOmdbSearch([$this->omdbMovie('tt0000001', 'No Poster', 'N/A')], '1');

        $this->getJson('/api/movies?q=poster')->assertOk()->assertJsonPath('data.0.poster', null);
    }

    public function test_unknown_title_is_an_empty_list_not_an_error(): void
    {
        Http::fake(['www.omdbapi.com/*' => Http::response(['Response' => 'False', 'Error' => 'Movie not found!'])]);

        $this->getJson('/api/movies?q=zzzzqqqxxyy')
            ->assertOk()
            ->assertExactJson([
                'data' => [],
                'meta' => ['page' => 1, 'total' => 0, 'per_page' => 10, 'last_page' => 1],
            ]);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidQueries(): array
    {
        return [
            'missing q' => ['/api/movies', 'q'],
            'empty q' => ['/api/movies?q=', 'q'],
            'blank q' => ['/api/movies?q=%20%20', 'q'],
            'q too long' => ['/api/movies?q='.str_repeat('a', 101), 'q'],
            'page zero' => ['/api/movies?q=batman&page=0', 'page'],
            'page too high' => ['/api/movies?q=batman&page=101', 'page'],
            'page not a number' => ['/api/movies?q=batman&page=abc', 'page'],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_input_is_rejected_without_calling_omdb(string $url, string $invalidField): void
    {
        Http::fake();

        $this->getJson($url)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($invalidField);

        Http::assertNothingSent();
    }
}
