export interface MovieSummary {
    imdb_id: string;
    title: string;
    year: string;
    type: string;
    poster: string | null;
}

export interface MovieRating {
    source: string;
    value: string;
}

export interface MovieDetails {
    imdb_id: string;
    title: string;
    year: string;
    type: string;
    rated: string | null;
    runtime: string | null;
    genres: string[];
    plot: string | null;
    director: string | null;
    writers: string[];
    actors: string[];
    awards: string | null;
    poster: string | null;
    ratings: MovieRating[];
    imdb_rating: number | null;
    imdb_votes: number | null;
}

export interface DetailsResponse {
    data: MovieDetails;
}

export interface PageMeta {
    page: number;
    total: number;
    per_page: number;
    last_page: number;
}

export interface HistoryResponse {
    data: string[];
}

export interface SearchResponse {
    data: MovieSummary[];
    meta: PageMeta;
}
