<?php

namespace Tests\Unit;

use App\Services\Omdb\OmdbErrorKind;
use App\Services\Omdb\OmdbException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OmdbExceptionTest extends TestCase
{
    /** @return array<string, array{OmdbErrorKind, int}> */
    public static function statuses(): array
    {
        return [
            'not found' => [OmdbErrorKind::NotFound, 404],
            'too many results' => [OmdbErrorKind::TooManyResults, 422],
            'rate limited' => [OmdbErrorKind::RateLimited, 429],
            'invalid key' => [OmdbErrorKind::InvalidKey, 500],
            'upstream' => [OmdbErrorKind::Upstream, 502],
        ];
    }

    #[DataProvider('statuses')]
    public function test_each_kind_maps_to_an_http_status(OmdbErrorKind $kind, int $status): void
    {
        $exception = new OmdbException($kind);

        $this->assertSame($status, $exception->status());
        $this->assertSame($kind, $exception->kind);
    }

    public function test_message_comes_from_the_kind(): void
    {
        $exception = new OmdbException(OmdbErrorKind::RateLimited);

        $this->assertSame(OmdbErrorKind::RateLimited->message(), $exception->getMessage());
    }

    public function test_keeps_the_previous_exception(): void
    {
        $cause = new \RuntimeException('connection refused');

        $exception = new OmdbException(OmdbErrorKind::Upstream, $cause);

        $this->assertSame($cause, $exception->getPrevious());
    }
}
