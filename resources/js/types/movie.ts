export interface MovieSummary {
    imdb_id: string;
    title: string;
    year: string;
    type: string;
    poster: string | null;
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
