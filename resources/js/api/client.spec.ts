import { AxiosError, type AxiosAdapter, type InternalAxiosRequestConfig } from 'axios';
import { afterEach, describe, expect, it } from 'vitest';
import type { SearchResponse } from '../types/movie';
import { ApiError, http, searchMovies } from './client';

const originalAdapter = http.defaults.adapter;

function respondWith(status: number, data: unknown): AxiosAdapter {
    return (config: InternalAxiosRequestConfig) => {
        const response = { data, status, statusText: '', headers: {}, config };

        return status < 400
            ? Promise.resolve(response)
            : Promise.reject(new AxiosError('Request failed', 'ERR_BAD_REQUEST', config, null, response));
    };
}

describe('searchMovies', () => {
    afterEach(() => {
        http.defaults.adapter = originalAdapter;
    });

    it('asks /api/movies with the text and the page, and returns the body', async () => {
        const body: SearchResponse = {
            data: [],
            meta: { page: 2, total: 0, per_page: 10, last_page: 1 },
        };
        let sent: InternalAxiosRequestConfig | undefined;
        http.defaults.adapter = (config) => {
            sent = config;

            return respondWith(200, body)(config);
        };

        const result = await searchMovies('batman', 2);

        expect(result).toEqual(body);
        expect(sent?.baseURL).toBe('/api');
        expect(sent?.url).toBe('/movies');
        expect(sent?.params).toEqual({ q: 'batman', page: 2 });
    });

    it('turns an error response into an ApiError with the server message and status', async () => {
        http.defaults.adapter = respondWith(422, { message: 'The q field is required.' });

        await expect(searchMovies('', 1)).rejects.toMatchObject({
            name: 'ApiError',
            message: 'The q field is required.',
            status: 422,
        });
    });

    it('uses a generic message when the error response has none', async () => {
        http.defaults.adapter = respondWith(500, '<html>oops</html>');

        await expect(searchMovies('batman', 1)).rejects.toMatchObject({
            message: 'Something went wrong.',
            status: 500,
        });
    });

    it('reports an unreachable server with a null status', async () => {
        http.defaults.adapter = (config) => Promise.reject(new AxiosError('Network Error', 'ERR_NETWORK', config));

        const error = await searchMovies('batman', 1).catch((e: unknown) => e);

        expect(error).toBeInstanceOf(ApiError);
        expect(error).toMatchObject({ status: null });
    });
});
