<?php

namespace Tests\Unit;

use App\Services\Omdb\OmdbClient;
use App\Services\Omdb\OmdbErrorKind;
use App\Services\Omdb\OmdbException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OmdbClientFindTest extends TestCase
{
    private const string BASE_URL = 'https://www.omdbapi.com/';

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
    }

    private function client(): OmdbClient
    {
        return new OmdbClient(self::BASE_URL, 'test-key');
    }

    /** An answer shaped like the ones OMDb really sent for tt0372784 (checked with a live request). */
    private function batmanBegins(array $overrides = []): array
    {
        return array_merge([
            'Title' => 'Batman Begins',
            'Year' => '2005',
            'Rated' => 'PG-13',
            'Released' => '15 Jun 2005',
            'Runtime' => '140 min',
            'Genre' => 'Crime',
            'Director' => 'Christopher Nolan',
            'Writer' => 'Bob Kane, David S. Goyer, Christopher Nolan',
            'Actors' => 'Christian Bale, Michael Caine, Ken Watanabe',
            'Plot' => 'Billionaire Bruce Wayne becomes Batman.',
            'Language' => 'English, Mandarin',
            'Country' => 'United States, United Kingdom',
            'Awards' => 'Nominated for 1 Oscar. 15 wins & 79 nominations total',
            'Poster' => 'https://example.com/begins.jpg',
            'Ratings' => [
                ['Source' => 'Internet Movie Database', 'Value' => '8.2/10'],
                ['Source' => 'Rotten Tomatoes', 'Value' => '85%'],
                ['Source' => 'Metacritic', 'Value' => '70/100'],
            ],
            'Metascore' => '70',
            'imdbRating' => '8.2',
            'imdbVotes' => '1,748,238',
            'imdbID' => 'tt0372784',
            'Type' => 'movie',
            'DVD' => 'N/A',
            'BoxOffice' => '$206,863,479',
            'Production' => 'N/A',
            'Website' => 'N/A',
            'Response' => 'True',
        ], $overrides);
    }

    private function fakeMovie(array $overrides = []): void
    {
        Http::fake([self::BASE_URL.'*' => Http::response($this->batmanBegins($overrides))]);
    }

    public function test_asks_omdb_for_the_id_with_the_full_plot(): void
    {
        $this->fakeMovie();

        $this->client()->find('tt0372784');

        Http::assertSent(fn (Request $request) => $request->url() === self::BASE_URL.'?apikey=test-key&i=tt0372784&plot=full');
    }

    public function test_returns_the_details_in_our_own_shape(): void
    {
        $this->fakeMovie();

        $this->assertSame([
            'imdb_id' => 'tt0372784',
            'title' => 'Batman Begins',
            'year' => '2005',
            'type' => 'movie',
            'rated' => 'PG-13',
            'runtime' => '140 min',
            'genres' => ['Crime'],
            'plot' => 'Billionaire Bruce Wayne becomes Batman.',
            'director' => 'Christopher Nolan',
            'writers' => ['Bob Kane', 'David S. Goyer', 'Christopher Nolan'],
            'actors' => ['Christian Bale', 'Michael Caine', 'Ken Watanabe'],
            'awards' => 'Nominated for 1 Oscar. 15 wins & 79 nominations total',
            'poster' => 'https://example.com/begins.jpg',
            'ratings' => [
                ['source' => 'Internet Movie Database', 'value' => '8.2/10'],
                ['source' => 'Rotten Tomatoes', 'value' => '85%'],
                ['source' => 'Metacritic', 'value' => '70/100'],
            ],
            'imdb_rating' => 8.2,
            'imdb_votes' => 1748238,
        ], $this->client()->find('tt0372784'));
    }

    public function test_comma_lists_become_arrays(): void
    {
        $this->fakeMovie(['Genre' => 'Documentary, Short']);

        $this->assertSame(['Documentary', 'Short'], $this->client()->find('tt0372784')['genres']);
    }

    public function test_values_omdb_does_not_have_become_null_or_empty_lists(): void
    {
        $this->fakeMovie([
            'Rated' => 'N/A',
            'Runtime' => 'N/A',
            'Genre' => 'N/A',
            'Plot' => 'N/A',
            'Director' => 'N/A',
            'Writer' => 'N/A',
            'Actors' => 'N/A',
            'Awards' => 'N/A',
            'Poster' => 'N/A',
            'Ratings' => [],
            'imdbRating' => 'N/A',
            'imdbVotes' => 'N/A',
        ]);

        $movie = $this->client()->find('tt0372784');

        $this->assertNull($movie['rated']);
        $this->assertNull($movie['runtime']);
        $this->assertNull($movie['plot']);
        $this->assertNull($movie['director']);
        $this->assertNull($movie['awards']);
        $this->assertNull($movie['poster']);
        $this->assertNull($movie['imdb_rating']);
        $this->assertNull($movie['imdb_votes']);
        $this->assertSame([], $movie['genres']);
        $this->assertSame([], $movie['writers']);
        $this->assertSame([], $movie['actors']);
        $this->assertSame([], $movie['ratings']);
    }

    public function test_an_answer_without_a_ratings_field_gives_no_ratings(): void
    {
        Http::fake([self::BASE_URL.'*' => Http::response(array_diff_key($this->batmanBegins(), ['Ratings' => 1]))]);

        $this->assertSame([], $this->client()->find('tt0372784')['ratings']);
    }

    public function test_incomplete_rating_entries_are_skipped(): void
    {
        $this->fakeMovie(['Ratings' => [
            ['Source' => 'Rotten Tomatoes'],
            ['Source' => 'Metacritic', 'Value' => '70/100'],
        ]]);

        $this->assertSame(
            [['source' => 'Metacritic', 'value' => '70/100']],
            $this->client()->find('tt0372784')['ratings'],
        );
    }

    public function test_repeated_lookup_is_served_from_the_cache(): void
    {
        $this->fakeMovie();

        $first = $this->client()->find('tt0372784');
        $second = $this->client()->find('tt0372784');

        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_each_id_is_cached_separately(): void
    {
        $this->fakeMovie();

        $this->client()->find('tt0372784');
        $this->client()->find('tt0000001');

        Http::assertSentCount(2);
    }

    public function test_failures_other_than_not_found_are_not_cached(): void
    {
        Http::fake([self::BASE_URL.'*' => Http::sequence()
            ->push(['Response' => 'False', 'Error' => 'Something unexpected'])
            ->push($this->batmanBegins())]);

        try {
            $this->client()->find('tt0372784');
            $this->fail('Expected an OmdbException.');
        } catch (OmdbException) {
        }

        $this->assertSame('Batman Begins', $this->client()->find('tt0372784')['title']);
    }

    public function test_missing_year_and_type_become_empty_text(): void
    {
        Http::fake([self::BASE_URL.'*' => Http::response(array_diff_key($this->batmanBegins(), ['Year' => 1, 'Type' => 1]))]);

        $movie = $this->client()->find('tt0372784');

        $this->assertSame('', $movie['year']);
        $this->assertSame('', $movie['type']);
    }

    public function test_an_unknown_id_is_remembered_for_a_short_while(): void
    {
        Http::fake([self::BASE_URL.'*' => Http::sequence()
            ->push(['Response' => 'False', 'Error' => 'Error getting data.'])
            ->push($this->batmanBegins())]);

        foreach ([1, 2] as $attempt) {
            try {
                $this->client()->find('tt0372784');
                $this->fail('Expected an OmdbException.');
            } catch (OmdbException $e) {
                $this->assertSame(OmdbErrorKind::NotFound, $e->kind);
            }
        }

        Http::assertSentCount(1);
    }

    public function test_an_unknown_id_is_asked_for_again_after_that_time(): void
    {
        Http::fake([self::BASE_URL.'*' => Http::sequence()
            ->push(['Response' => 'False', 'Error' => 'Error getting data.'])
            ->push($this->batmanBegins())]);

        try {
            $this->client()->find('tt0372784');
        } catch (OmdbException) {
        }

        $this->travel(11)->minutes();

        $this->assertSame('Batman Begins', $this->client()->find('tt0372784')['title']);
    }

    /** @return array<string, array{string, mixed}> */
    public static function unusableEssentialFields(): array
    {
        return [
            'missing id' => ['imdbID', null],
            'empty id' => ['imdbID', ''],
            'blank id' => ['imdbID', '   '],
            'id N/A' => ['imdbID', 'N/A'],
            'missing title' => ['Title', null],
            'empty title' => ['Title', ''],
            'blank title' => ['Title', '   '],
            'title N/A' => ['Title', 'N/A'],
            'title that is not text' => ['Title', 123],
        ];
    }

    #[DataProvider('unusableEssentialFields')]
    public function test_an_answer_without_a_usable_id_or_title_is_an_upstream_error(string $field, mixed $value): void
    {
        $answer = $value === null
            ? array_diff_key($this->batmanBegins(), [$field => 1])
            : array_merge($this->batmanBegins(), [$field => $value]);
        Http::fake([self::BASE_URL.'*' => Http::response($answer)]);

        try {
            $this->client()->find('tt0372784');
            $this->fail('Expected an OmdbException.');
        } catch (OmdbException $e) {
            $this->assertSame(OmdbErrorKind::Upstream, $e->kind);
        }
    }

    /** @return array<string, array{string, int, OmdbErrorKind}> */
    public static function errorResponses(): array
    {
        return [
            'malformed id' => ['Incorrect IMDb ID.', 200, OmdbErrorKind::NotFound],
            'id that does not exist' => ['Error getting data.', 200, OmdbErrorKind::NotFound],
            'invalid key' => ['Invalid API key!', 401, OmdbErrorKind::InvalidKey],
            'request limit' => ['Request limit reached!', 401, OmdbErrorKind::RateLimited],
        ];
    }

    #[DataProvider('errorResponses')]
    public function test_omdb_errors_become_exceptions(string $error, int $httpStatus, OmdbErrorKind $kind): void
    {
        Http::fake([self::BASE_URL.'*' => Http::response(['Response' => 'False', 'Error' => $error], $httpStatus)]);

        try {
            $this->client()->find('tt0000000');
            $this->fail('Expected an OmdbException.');
        } catch (OmdbException $e) {
            $this->assertSame($kind, $e->kind);
        }
    }
}
