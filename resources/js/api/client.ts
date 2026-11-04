import axios, { isAxiosError } from 'axios';
import type { SearchResponse } from '../types/movie';

export class ApiError extends Error {
    /** HTTP status, or null when the server could not be reached at all. */
    readonly status: number | null;

    constructor(message: string, status: number | null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
    }
}

export const http = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

/** Turns whatever axios throws into an ApiError that carries a message fit for the user. */
function toApiError(error: unknown): unknown {
    if (!isAxiosError<{ message?: string }>(error)) {
        return error;
    }

    if (error.response) {
        return new ApiError(error.response.data?.message ?? 'Something went wrong.', error.response.status);
    }

    // A cancelled request has no response either, but the caller already knows it cancelled it.
    if (error.code === 'ERR_CANCELED') {
        return error;
    }

    return new ApiError('Cannot reach the server. Check your connection and try again.', null);
}

export async function searchMovies(query: string, page: number, signal?: AbortSignal): Promise<SearchResponse> {
    try {
        const { data } = await http.get<SearchResponse>('/movies', { params: { q: query, page }, signal });

        return data;
    } catch (error) {
        throw toApiError(error);
    }
}
