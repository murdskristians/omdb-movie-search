<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecordSearchHistoryTest extends TestCase
{
    private function omdbFinds(string $title): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Search' => [
                    ['Title' => $title, 'Year' => '2005', 'imdbID' => 'tt0000001', 'Type' => 'movie', 'Poster' => 'N/A'],
                ],
                'totalResults' => '1',
                'Response' => 'True',
            ]),
        ]);
    }

    private function history(): array
    {
        return $this->getJson('/api/history')->json('data');
    }

    public function test_a_search_with_results_is_recorded(): void
    {
        $this->omdbFinds('Batman Begins');

        $this->getJson('/api/movies?q=batman')->assertOk();

        $this->assertSame(['batman'], $this->history());
    }

    public function test_the_newest_search_comes_first(): void
    {
        $this->omdbFinds('Some movie');

        $this->getJson('/api/movies?q=batman');
        $this->getJson('/api/movies?q=matrix');

        $this->assertSame(['matrix', 'batman'], $this->history());
    }

    public function test_a_search_answered_from_the_cache_is_still_recorded(): void
    {
        $this->omdbFinds('Some movie');

        $this->getJson('/api/movies?q=batman');
        $this->getJson('/api/movies?q=matrix');
        Http::fake(fn () => throw new \LogicException('OMDb must not be called again.'));
        $this->getJson('/api/movies?q=batman')->assertOk();

        $this->assertSame(['batman', 'matrix'], $this->history());
    }

    public function test_a_search_without_results_is_not_recorded(): void
    {
        Http::fake(['www.omdbapi.com/*' => Http::response(['Response' => 'False', 'Error' => 'Movie not found!'])]);

        $this->getJson('/api/movies?q=zzzzqqq')->assertOk()->assertJsonPath('data', []);

        $this->assertSame([], $this->history());
    }

    public function test_a_failed_search_is_not_recorded(): void
    {
        Http::fake(['www.omdbapi.com/*' => Http::response(['Response' => 'False', 'Error' => 'Too many results.'])]);

        $this->getJson('/api/movies?q=the')->assertStatus(422);

        $this->assertSame([], $this->history());
    }

    public function test_an_invalid_search_is_not_recorded(): void
    {
        Http::fake();

        $this->getJson('/api/movies?q=')->assertStatus(422);

        $this->assertSame([], $this->history());
    }

    public function test_browsing_to_a_later_page_does_not_count_as_a_new_search(): void
    {
        $this->omdbFinds('Some movie');

        $this->getJson('/api/movies?q=batman');
        $this->getJson('/api/movies?q=matrix');
        $this->getJson('/api/movies?q=batman&page=2');

        $this->assertSame(['matrix', 'batman'], $this->history());
    }
}
