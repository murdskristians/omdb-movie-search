<?php

namespace App\Services\Omdb;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class OmdbClient
{
    /** Number of results OMDb returns on one search page. */
    public const PAGE_SIZE = 10;

    /** OMDb allows 1000 requests a day, so repeated searches are kept for a while. */
    private const SEARCH_CACHE_SECONDS = 600;

    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
    ) {}

    /**
     * Search movies by title. OMDb returns up to 10 results per page.
     * A title nobody has heard of is not an error here, it is an empty list.
     *
     * @return array{
     *     items: list<array{imdb_id: string, title: string, year: string, type: string, poster: ?string}>,
     *     total: int
     * }
     *
     * @throws OmdbException
     */
    public function search(string $query, int $page = 1): array
    {
        // Only finished answers are cached. When fetchSearch() throws, nothing is stored.
        return Cache::remember(
            $this->searchCacheKey($query, $page),
            self::SEARCH_CACHE_SECONDS,
            fn () => $this->fetchSearch($query, $page),
        );
    }

    /** @throws OmdbException */
    private function fetchSearch(string $query, int $page): array
    {
        try {
            $body = $this->request([
                's' => $query,
                'page' => $page,
            ]);
        } catch (OmdbException $e) {
            if ($e->kind === OmdbErrorKind::NotFound) {
                return ['items' => [], 'total' => 0];
            }

            throw $e;
        }

        return [
            'items' => $this->normalizeSummaries($body['Search'] ?? null),
            'total' => (int) ($body['totalResults'] ?? 0),
        ];
    }

    /**
     * Everything OMDb knows about one movie. Values OMDb has no answer for come back as null
     * (or as an empty list for the list fields).
     *
     * @return array{
     *     imdb_id: string,
     *     title: string,
     *     year: string,
     *     type: string,
     *     rated: ?string,
     *     runtime: ?string,
     *     genres: list<string>,
     *     plot: ?string,
     *     director: ?string,
     *     writers: list<string>,
     *     actors: list<string>,
     *     awards: ?string,
     *     poster: ?string,
     *     ratings: list<array{source: string, value: string}>,
     *     imdb_rating: ?float,
     *     imdb_votes: ?int
     * }
     *
     * @throws OmdbException
     */
    public function find(string $imdbId): array
    {
        $body = $this->request(['i' => $imdbId, 'plot' => 'full']);

        // An answer that says "True" but has no id or title is a broken answer, not a movie.
        if (! $this->hasIdentity($body)) {
            throw new OmdbException(OmdbErrorKind::Upstream);
        }

        return [
            'imdb_id' => $body['imdbID'],
            'title' => $body['Title'],
            'year' => $body['Year'] ?? '',
            'type' => $body['Type'] ?? '',
            'rated' => $this->nullIfMissing($body['Rated'] ?? null),
            'runtime' => $this->nullIfMissing($body['Runtime'] ?? null),
            'genres' => $this->splitList($body['Genre'] ?? null),
            'plot' => $this->nullIfMissing($body['Plot'] ?? null),
            'director' => $this->nullIfMissing($body['Director'] ?? null),
            'writers' => $this->splitList($body['Writer'] ?? null),
            'actors' => $this->splitList($body['Actors'] ?? null),
            'awards' => $this->nullIfMissing($body['Awards'] ?? null),
            'poster' => $this->nullIfMissing($body['Poster'] ?? null),
            'ratings' => $this->normalizeRatings($body['Ratings'] ?? []),
            'imdb_rating' => $this->parseRating($body['imdbRating'] ?? null),
            'imdb_votes' => $this->parseVotes($body['imdbVotes'] ?? null),
        ];
    }

    /** "Batman" and " batman " are the same search, so they share one cache entry. */
    private function searchCacheKey(string $query, int $page): string
    {
        return 'omdb:search:'.md5(mb_strtolower(trim($query))).':'.$page;
    }

    /**
     * @param  array<string, string|int>  $params
     *
     * @throws OmdbException
     */
    private function request(array $params): array
    {
        try {
            $response = Http::timeout(5)
                ->retry(2, 200, $this->isTransient(...), throw: false)
                ->get($this->baseUrl, ['apikey' => $this->apiKey, ...$params]);
        } catch (ConnectionException $e) {
            throw new OmdbException(OmdbErrorKind::Upstream, $e);
        }

        $body = $response->json();

        if ($response->serverError() || ! is_array($body)) {
            throw new OmdbException(OmdbErrorKind::Upstream);
        }

        if (($body['Response'] ?? null) === 'False') {
            throw new OmdbException($this->kindFor($body['Error'] ?? ''));
        }

        return $body;
    }

    /** Only network problems and 5xx answers are worth another attempt. */
    private function isTransient(Throwable $e): bool
    {
        return $e instanceof ConnectionException
            || ($e instanceof RequestException && $e->response->serverError());
    }

    /** OMDb reports most errors with HTTP 200, so the message text is all we have. */
    private function kindFor(string $error): OmdbErrorKind
    {
        $error = strtolower($error);

        return match (true) {
            str_contains($error, 'not found'),
            str_contains($error, 'incorrect imdb id'),
            str_contains($error, 'error getting data') => OmdbErrorKind::NotFound,
            str_contains($error, 'too many results') => OmdbErrorKind::TooManyResults,
            str_contains($error, 'limit') => OmdbErrorKind::RateLimited,
            str_contains($error, 'api key') => OmdbErrorKind::InvalidKey,
            default => OmdbErrorKind::Upstream,
        };
    }

    /**
     * One damaged entry must not spoil the other results, so entries without an id or a title are left out.
     *
     * @return list<array{imdb_id: string, title: string, year: string, type: string, poster: ?string}>
     */
    private function normalizeSummaries(mixed $results): array
    {
        $summaries = [];

        foreach (is_array($results) ? $results : [] as $item) {
            if ($this->hasIdentity($item)) {
                $summaries[] = [
                    'imdb_id' => $item['imdbID'],
                    'title' => $item['Title'],
                    'year' => $item['Year'] ?? '',
                    'type' => $item['Type'] ?? '',
                    'poster' => $this->nullIfMissing($item['Poster'] ?? null),
                ];
            }
        }

        return $summaries;
    }

    /** A movie we cannot identify or name is of no use to the screen. */
    private function hasIdentity(mixed $item): bool
    {
        return is_array($item)
            && is_string($item['imdbID'] ?? null)
            && is_string($item['Title'] ?? null);
    }

    /**
     * OMDb sends lists as one string: "Crime, Drama".
     *
     * @return list<string>
     */
    private function splitList(?string $value): array
    {
        $value = $this->nullIfMissing($value);

        return $value === null ? [] : array_map('trim', explode(',', $value));
    }

    /**
     * @param  list<array{Source?: string, Value?: string}>  $ratings
     * @return list<array{source: string, value: string}>
     */
    private function normalizeRatings(array $ratings): array
    {
        $normalized = [];

        foreach ($ratings as $rating) {
            if (isset($rating['Source'], $rating['Value'])) {
                $normalized[] = ['source' => $rating['Source'], 'value' => $rating['Value']];
            }
        }

        return $normalized;
    }

    private function parseRating(?string $value): ?float
    {
        $value = $this->nullIfMissing($value);

        return $value === null ? null : (float) $value;
    }

    /** OMDb writes the votes with thousands separators: "1,748,238". */
    private function parseVotes(?string $value): ?int
    {
        $value = $this->nullIfMissing($value);

        return $value === null ? null : (int) str_replace(',', '', $value);
    }

    /** OMDb writes "N/A" when it has no value. */
    private function nullIfMissing(?string $value): ?string
    {
        return $value === null || $value === '' || $value === 'N/A' ? null : $value;
    }
}
