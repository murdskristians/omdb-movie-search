<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
    }

    /** @return array<string, array{string, int, int, string}> */
    public static function omdbFailures(): array
    {
        return [
            'too many results' => ['Too many results.', 200, 422, 'Too many results. Try a more specific title.'],
            'request limit' => ['Request limit reached!', 401, 429, 'Daily request limit reached. Please try again later.'],
            'invalid key' => ['Invalid API key!', 401, 500, 'The movie service is not configured correctly.'],
        ];
    }

    #[DataProvider('omdbFailures')]
    public function test_omdb_failures_become_json_messages_with_the_right_status(
        string $omdbError,
        int $omdbStatus,
        int $expectedStatus,
        string $expectedMessage,
    ): void {
        Http::fake(['www.omdbapi.com/*' => Http::response(['Response' => 'False', 'Error' => $omdbError], $omdbStatus)]);

        $this->getJson('/api/movies?q=batman')
            ->assertStatus($expectedStatus)
            ->assertExactJson(['message' => $expectedMessage]);
    }

    public function test_unreachable_omdb_is_a_502(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->getJson('/api/movies?q=batman')
            ->assertStatus(502)
            ->assertExactJson(['message' => 'The movie service is currently unavailable.']);
    }

    public function test_errors_never_reveal_the_api_key(): void
    {
        config(['services.omdb.key' => 'super-secret-key']);
        Http::fake(['www.omdbapi.com/*' => Http::response(['Response' => 'False', 'Error' => 'Invalid API key!'], 401)]);

        $response = $this->getJson('/api/movies?q=batman');

        $this->assertStringNotContainsString('super-secret-key', $response->getContent());
    }

    public function test_api_errors_are_json_even_without_an_accept_header(): void
    {
        $this->get('/api/movies')
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonValidationErrors('q');
    }

    public function test_unknown_api_route_is_a_json_404(): void
    {
        $this->get('/api/nothing-here')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }
}
