<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MovieDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
    }

    private function fakeMovie(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Title' => 'Batman Begins',
                'Year' => '2005',
                'Rated' => 'PG-13',
                'Runtime' => '140 min',
                'Genre' => 'Action, Crime',
                'Director' => 'Christopher Nolan',
                'Writer' => 'Bob Kane, David S. Goyer',
                'Actors' => 'Christian Bale, Michael Caine',
                'Plot' => 'Billionaire Bruce Wayne becomes Batman.',
                'Awards' => 'N/A',
                'Poster' => 'https://example.com/begins.jpg',
                'Ratings' => [['Source' => 'Internet Movie Database', 'Value' => '8.2/10']],
                'imdbRating' => '8.2',
                'imdbVotes' => '1,748,238',
                'imdbID' => 'tt0372784',
                'Type' => 'movie',
                'Response' => 'True',
            ]),
        ]);
    }

    public function test_returns_the_details_of_a_movie(): void
    {
        $this->fakeMovie();

        $this->getJson('/api/movies/tt0372784')
            ->assertOk()
            ->assertExactJson(['data' => [
                'imdb_id' => 'tt0372784',
                'title' => 'Batman Begins',
                'year' => '2005',
                'type' => 'movie',
                'rated' => 'PG-13',
                'runtime' => '140 min',
                'genres' => ['Action', 'Crime'],
                'plot' => 'Billionaire Bruce Wayne becomes Batman.',
                'director' => 'Christopher Nolan',
                'writers' => ['Bob Kane', 'David S. Goyer'],
                'actors' => ['Christian Bale', 'Michael Caine'],
                'awards' => null,
                'poster' => 'https://example.com/begins.jpg',
                'ratings' => [['source' => 'Internet Movie Database', 'value' => '8.2/10']],
                'imdb_rating' => 8.2,
                'imdb_votes' => 1748238,
            ]]);
    }

    public function test_asks_omdb_for_the_id_from_the_url(): void
    {
        $this->fakeMovie();

        $this->getJson('/api/movies/tt0372784');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'i=tt0372784'));
    }

    public function test_an_unknown_id_is_a_404_with_a_message(): void
    {
        Http::fake(['www.omdbapi.com/*' => Http::response(['Response' => 'False', 'Error' => 'Error getting data.'])]);

        $this->getJson('/api/movies/tt9999999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Movie not found.']);
    }

    /** @return array<string, array{string}> */
    public static function badIds(): array
    {
        return [
            'no digits' => ['tt'],
            'letters after tt' => ['tt12ab'],
            'no prefix' => ['0372784'],
            'a word' => ['batman'],
            'upper case prefix' => ['TT0372784'],
        ];
    }

    #[DataProvider('badIds')]
    public function test_an_id_in_the_wrong_format_is_a_404_and_never_reaches_omdb(string $id): void
    {
        Http::fake();

        $this->getJson('/api/movies/'.$id)->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_unreachable_omdb_is_a_502(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->getJson('/api/movies/tt0372784')
            ->assertStatus(502)
            ->assertExactJson(['message' => 'The movie service is currently unavailable.']);
    }

    public function test_searching_still_works_next_to_the_details_route(): void
    {
        Http::fake(['www.omdbapi.com/*' => Http::response(['Search' => [], 'totalResults' => '0', 'Response' => 'True'])]);

        $this->getJson('/api/movies?q=batman')->assertOk()->assertJsonPath('data', []);
    }
}
