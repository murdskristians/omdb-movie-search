<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One movie with all its details, as returned by OmdbClient::find().
 */
class MovieDetailsResource extends JsonResource
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
            'rated' => $this->resource['rated'],
            'runtime' => $this->resource['runtime'],
            'genres' => $this->resource['genres'],
            'plot' => $this->resource['plot'],
            'director' => $this->resource['director'],
            'writers' => $this->resource['writers'],
            'actors' => $this->resource['actors'],
            'awards' => $this->resource['awards'],
            'poster' => $this->resource['poster'],
            'ratings' => $this->resource['ratings'],
            'imdb_rating' => $this->resource['imdb_rating'],
            'imdb_votes' => $this->resource['imdb_votes'],
        ];
    }
}
