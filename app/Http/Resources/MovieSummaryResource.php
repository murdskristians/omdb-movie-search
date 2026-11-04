<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One search result, as returned by OmdbClient::search().
 */
class MovieSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'imdb_id' => $this->resource['imdb_id'],
            'title' => $this->resource['title'],
            'year' => $this->resource['year'],
            'type' => $this->resource['type'],
            'poster' => $this->resource['poster'],
        ];
    }
}
