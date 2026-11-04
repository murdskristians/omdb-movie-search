<?php

namespace App\Services\Omdb;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OmdbClient
{
    /** Number of results OMDb returns on one search page. */
    public const PAGE_SIZE = 10;

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
            'items' => array_map($this->normalizeSummary(...), $body['Search'] ?? []),
            'total' => (int) ($body['totalResults'] ?? 0),
        ];
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
            str_contains($error, 'incorrect imdb id') => OmdbErrorKind::NotFound,
            str_contains($error, 'too many results') => OmdbErrorKind::TooManyResults,
            str_contains($error, 'limit') => OmdbErrorKind::RateLimited,
            str_contains($error, 'api key') => OmdbErrorKind::InvalidKey,
            default => OmdbErrorKind::Upstream,
        };
    }

    private function normalizeSummary(array $item): array
    {
        return [
            'imdb_id' => $item['imdbID'],
            'title' => $item['Title'],
            'year' => $item['Year'],
            'type' => $item['Type'],
            'poster' => $this->nullIfMissing($item['Poster'] ?? null),
        ];
    }

    /** OMDb writes "N/A" when it has no value. */
    private function nullIfMissing(?string $value): ?string
    {
        return $value === null || $value === '' || $value === 'N/A' ? null : $value;
    }
}
