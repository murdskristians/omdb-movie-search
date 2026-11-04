<?php

namespace App\Services\Omdb;

use RuntimeException;
use Throwable;

class OmdbException extends RuntimeException
{
    public function __construct(
        public readonly OmdbErrorKind $kind,
        ?Throwable $previous = null,
    ) {
        parent::__construct($kind->message(), 0, $previous);
    }

    public function status(): int
    {
        return $this->kind->status();
    }
}
