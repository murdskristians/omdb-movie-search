<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/probe-proxy', fn () => [
            'secure' => request()->isSecure(),
            'asset' => asset('build/app.css'),
        ]);
    }

    public function test_a_request_that_came_through_the_hosts_proxy_over_https_counts_as_https(): void
    {
        $this->getJson('/probe-proxy', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'movies.example.com'])
            ->assertOk()
            ->assertJsonPath('secure', true)
            ->assertJsonPath('asset', 'https://movies.example.com/build/app.css');
    }

    public function test_without_the_proxy_headers_it_stays_plain_http(): void
    {
        $this->getJson('/probe-proxy')
            ->assertOk()
            ->assertJsonPath('secure', false);
    }
}
