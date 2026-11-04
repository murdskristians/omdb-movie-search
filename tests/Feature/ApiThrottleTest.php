<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiThrottleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get('/api/throttle-probe', fn () => ['ok' => true]);
    }

    public function test_api_routes_answer_to_the_api_prefix(): void
    {
        $this->getJson('/api/throttle-probe')->assertOk()->assertJson(['ok' => true]);
    }

    public function test_api_allows_60_requests_per_minute_then_answers_429(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/throttle-probe')->assertOk();
        }

        $this->getJson('/api/throttle-probe')->assertStatus(429);
    }
}
