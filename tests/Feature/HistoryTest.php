<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HistoryTest extends TestCase
{
    public function test_history_is_empty_for_a_new_visitor(): void
    {
        $this->getJson('/api/history')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_it_returns_the_saved_queries_newest_first(): void
    {
        $this->withSession(['search_history' => ['matrix', 'batman']])
            ->getJson('/api/history')
            ->assertOk()
            ->assertExactJson(['data' => ['matrix', 'batman']]);
    }

    public function test_history_stays_in_the_session_between_requests(): void
    {
        $this->withSession(['search_history' => ['batman']]);

        $this->getJson('/api/history')->assertExactJson(['data' => ['batman']]);
        $this->getJson('/api/history')->assertExactJson(['data' => ['batman']]);
    }

    public function test_delete_clears_the_history(): void
    {
        $this->withSession(['search_history' => ['matrix', 'batman']]);

        $this->deleteJson('/api/history')->assertNoContent();

        $this->getJson('/api/history')->assertExactJson(['data' => []]);
    }

    public function test_history_requests_are_limited_to_60_a_minute(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/history')->assertOk();
        }

        $this->getJson('/api/history')->assertStatus(429);
        $this->deleteJson('/api/history')->assertStatus(429);
    }

    public function test_history_requests_do_not_use_up_the_search_limit(): void
    {
        Http::fake(['www.omdbapi.com/*' => Http::response(['Search' => [], 'totalResults' => '0', 'Response' => 'True'])]);

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/history')->assertOk();
        }

        $this->getJson('/api/movies?q=batman')->assertOk();
    }

    public function test_the_response_starts_a_session_and_hands_out_the_csrf_cookie(): void
    {
        $this->getJson('/api/history')
            ->assertCookie(config('session.cookie'))
            ->assertCookie('XSRF-TOKEN');
    }
}
