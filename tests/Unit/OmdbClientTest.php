<?php

namespace Tests\Unit;

use App\Services\Omdb\OmdbClient;
use App\Services\Omdb\OmdbErrorKind;
use App\Services\Omdb\OmdbException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OmdbClientTest extends TestCase
{
    private const BASE_URL = 'https://www.omdbapi.com/';

    protected function setUp(): void
    {
        parent::setUp();

        // Retries would otherwise really wait between attempts.
        Sleep::fake();
    }

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

    public function test_repeated_search_is_served_from_cache(): void
    {
        $this->fakeSearch([
            ['Title' => 'Batman Begins', 'Year' => '2005', 'imdbID' => 'tt0372784', 'Type' => 'movie', 'Poster' => 'N/A'],
        ], '1');

        $first = $this->client()->search('batman');
        $second = $this->client()->search('batman');

        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_cache_ignores_letter_case_and_surrounding_spaces(): void
    {
        $this->fakeSearch();

        $this->client()->search('Batman');
        $this->client()->search('  batman ');

        Http::assertSentCount(1);
    }

    public function test_each_page_is_cached_separately(): void
    {
        $this->fakeSearch();

        $this->client()->search('batman', 1);
        $this->client()->search('batman', 2);
        $this->client()->search('batman', 2);

        Http::assertSentCount(2);
    }

    public function test_unknown_title_is_cached_too(): void
    {
        Http::fake([self::BASE_URL.'*' => Http::response(['Response' => 'False', 'Error' => 'Movie not found!'])]);

        $this->client()->search('zzzzqqqxxyy');
        $this->client()->search('zzzzqqqxxyy');

        Http::assertSentCount(1);
    }

    public function test_failures_are_not_cached(): void
    {
        Http::fake([
            self::BASE_URL.'*' => Http::sequence()
                ->push(['Response' => 'False', 'Error' => 'Request limit reached!'], 401)
                ->push(['Search' => [], 'totalResults' => '0', 'Response' => 'True']),
        ]);

        try {
            $this->client()->search('batman');
            $this->fail('Expected an OmdbException.');
        } catch (OmdbException) {
            // The first attempt fails, the second one must reach OMDb again.
        }

        $this->assertSame([], $this->client()->search('batman')['items']);
        Http::assertSentCount(2);
    }

    public function test_unknown_title_gives_an_empty_result(): void
    {
        Http::fake([self::BASE_URL.'*' => Http::response(['Response' => 'False', 'Error' => 'Movie not found!'])]);

        $this->assertSame(['items' => [], 'total' => 0], $this->client()->search('zzzzqqqxxyy'));
    }

    /** @return array<string, array{string, int, OmdbErrorKind}> */
    public static function errorResponses(): array
    {
        return [
            'too many results' => ['Too many results.', 200, OmdbErrorKind::TooManyResults],
            'invalid key' => ['Invalid API key!', 401, OmdbErrorKind::InvalidKey],
            'missing key' => ['No API key provided.', 401, OmdbErrorKind::InvalidKey],
            'request limit' => ['Request limit reached!', 401, OmdbErrorKind::RateLimited],
            'something new' => ['Something unexpected', 200, OmdbErrorKind::Upstream],
        ];
    }

    #[DataProvider('errorResponses')]
    public function test_omdb_errors_become_exceptions(string $error, int $httpStatus, OmdbErrorKind $kind): void
    {
        Http::fake([self::BASE_URL.'*' => Http::response(['Response' => 'False', 'Error' => $error], $httpStatus)]);

        try {
            $this->client()->search('batman');
            $this->fail('Expected an OmdbException.');
        } catch (OmdbException $e) {
            $this->assertSame($kind, $e->kind);
        }

        Http::assertSentCount(1);
    }

    public function test_connection_failure_becomes_upstream_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        try {
            $this->client()->search('batman');
            $this->fail('Expected an OmdbException.');
        } catch (OmdbException $e) {
            $this->assertSame(OmdbErrorKind::Upstream, $e->kind);
            $this->assertInstanceOf(ConnectionException::class, $e->getPrevious());
        }
    }

    public function test_retries_after_a_server_error(): void
    {
        Http::fake([
            self::BASE_URL.'*' => Http::sequence()
                ->push('Service Unavailable', 503)
                ->push(['Search' => [], 'totalResults' => '0', 'Response' => 'True']),
        ]);

        $result = $this->client()->search('batman');

        $this->assertSame([], $result['items']);
        Http::assertSentCount(2);
    }

    public function test_gives_up_with_upstream_error_when_server_keeps_failing(): void
    {
        Http::fake([self::BASE_URL.'*' => Http::response('Bad Gateway', 502)]);

        try {
            $this->client()->search('batman');
            $this->fail('Expected an OmdbException.');
        } catch (OmdbException $e) {
            $this->assertSame(OmdbErrorKind::Upstream, $e->kind);
        }
    }

    public function test_response_that_is_not_json_becomes_upstream_error(): void
    {
        Http::fake([self::BASE_URL.'*' => Http::response('<html>oops</html>', 200)]);

        $this->expectException(OmdbException::class);

        $this->client()->search('batman');
    }

    public function test_entries_without_an_id_or_a_title_are_left_out(): void
    {
        $this->fakeSearch([
            ['Title' => 'Batman Begins', 'Year' => '2005', 'imdbID' => 'tt0372784', 'Type' => 'movie', 'Poster' => 'N/A'],
            ['Year' => '2006', 'imdbID' => 'tt0000002', 'Type' => 'movie', 'Poster' => 'N/A'],
            ['Title' => 'No id', 'Year' => '2007', 'Type' => 'movie', 'Poster' => 'N/A'],
            'not even an entry',
            ['Title' => 'The Dark Knight', 'Year' => '2008', 'imdbID' => 'tt0468569', 'Type' => 'movie', 'Poster' => 'N/A'],
        ], '5');

        $titles = array_column($this->client()->search('batman')['items'], 'title');

        $this->assertSame(['Batman Begins', 'The Dark Knight'], $titles);
    }

    public function test_missing_year_and_type_become_empty_text(): void
    {
        $this->fakeSearch([['Title' => 'Batman Begins', 'imdbID' => 'tt0372784']], '1');

        $item = $this->client()->search('batman')['items'][0];

        $this->assertSame('', $item['year']);
        $this->assertSame('', $item['type']);
        $this->assertNull($item['poster']);
    }

    public function test_a_search_field_that_is_not_a_list_gives_no_results(): void
    {
        Http::fake([self::BASE_URL.'*' => Http::response(['Search' => 'oops', 'totalResults' => '1', 'Response' => 'True'])]);

        $this->assertSame([], $this->client()->search('batman')['items']);
    }
}
