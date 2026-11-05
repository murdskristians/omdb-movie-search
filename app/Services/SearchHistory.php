<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;

/** The visitor's most recent search queries, kept in their session. */
class SearchHistory
{
    public const int LIMIT = 5;

    private const string KEY = 'search_history';

    public function __construct(private readonly Session $session) {}

    /** @return list<string> Newest first. */
    public function all(): array
    {
        return $this->session->get(self::KEY, []);
    }

    public function record(string $query): void
    {
        $query = trim($query);

        if ($query === '') {
            return;
        }

        $others = array_filter(
            $this->all(),
            fn (string $saved): bool => mb_strtolower($saved) !== mb_strtolower($query),
        );

        $this->session->put(self::KEY, array_slice([$query, ...$others], 0, self::LIMIT));
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }
}
