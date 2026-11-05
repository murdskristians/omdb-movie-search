<?php

namespace App\Services\Omdb;

enum OmdbErrorKind: string
{
    case NotFound = 'not_found';
    case TooManyResults = 'too_many_results';
    case RateLimited = 'rate_limited';
    case InvalidKey = 'invalid_key';
    case Upstream = 'upstream';
    case Timeout = 'timeout';

    /** HTTP status our API answers with for this kind of failure. */
    public function status(): int
    {
        return match ($this) {
            self::NotFound => 404,
            self::TooManyResults => 422,
            self::RateLimited => 429,
            self::InvalidKey => 500,
            self::Upstream => 502,
            self::Timeout => 504,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::NotFound => 'Movie not found.',
            self::TooManyResults => 'Too many results. Try a more specific title.',
            self::RateLimited => 'Daily request limit reached. Please try again later.',
            self::InvalidKey => 'The movie service is not configured correctly.',
            self::Upstream => 'The movie service is currently unavailable.',
            self::Timeout => 'The movie service took too long to answer. Please try again.',
        };
    }
}
