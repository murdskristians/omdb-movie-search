<?php

namespace Tests\Unit;

use App\Services\SearchHistory;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\TestCase;

class SearchHistoryTest extends TestCase
{
    private function history(): SearchHistory
    {
        return new SearchHistory(new Store('test', new ArraySessionHandler(120)));
    }

    public function test_it_starts_empty(): void
    {
        $this->assertSame([], $this->history()->all());
    }

    public function test_newest_query_comes_first(): void
    {
        $history = $this->history();

        $history->record('batman');
        $history->record('matrix');

        $this->assertSame(['matrix', 'batman'], $history->all());
    }

    public function test_it_keeps_only_the_last_five(): void
    {
        $history = $this->history();

        foreach (['one', 'two', 'three', 'four', 'five', 'six', 'seven'] as $query) {
            $history->record($query);
        }

        $this->assertSame(['seven', 'six', 'five', 'four', 'three'], $history->all());
    }

    public function test_a_repeated_query_moves_to_the_top_without_a_duplicate(): void
    {
        $history = $this->history();

        $history->record('batman');
        $history->record('matrix');
        $history->record('batman');

        $this->assertSame(['batman', 'matrix'], $history->all());
    }

    public function test_duplicates_are_found_regardless_of_case_and_the_newest_spelling_wins(): void
    {
        $history = $this->history();

        $history->record('Batman');
        $history->record('matrix');
        $history->record('BATMAN');

        $this->assertSame(['BATMAN', 'matrix'], $history->all());
    }

    public function test_it_trims_the_query(): void
    {
        $history = $this->history();

        $history->record('  batman ');
        $history->record('batman');

        $this->assertSame(['batman'], $history->all());
    }

    public function test_blank_queries_are_ignored(): void
    {
        $history = $this->history();

        $history->record('batman');
        $history->record('');
        $history->record('   ');

        $this->assertSame(['batman'], $history->all());
    }

    public function test_clear_removes_everything(): void
    {
        $history = $this->history();
        $history->record('batman');

        $history->clear();

        $this->assertSame([], $history->all());
    }

    public function test_a_duplicate_does_not_push_older_queries_out(): void
    {
        $history = $this->history();

        foreach (['one', 'two', 'three', 'four', 'five'] as $query) {
            $history->record($query);
        }
        $history->record('three');

        $this->assertSame(['three', 'five', 'four', 'two', 'one'], $history->all());
    }
}
