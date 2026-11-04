<?php

namespace App\Services\Omdb;

use Illuminate\Support\Facades\Http;

class OmdbClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
    ) {}

    /**
     * Search movies by title. OMDb returns up to 10 results per page.
     *
     * @return array{
     *     items: list<array{imdb_id: string, title: string, year: string, type: string, poster: ?string}>,
     *     total: int
     * }
     */
    public function search(string $query, int $page = 1): array
    {
        $body = $this->request([
            's' => $query,
            'page' => $page,
        ]);

        return [
            'items' => array_map($this->normalizeSummary(...), $body['Search'] ?? []),
            'total' => (int) ($body['totalResults'] ?? 0),
        ];
    }

    /** @param array<string, string|int> $params */
    private function request(array $params): array
    {
        return Http::timeout(5)
            ->get($this->baseUrl, ['apikey' => $this->apiKey, ...$params])
            ->json();
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
