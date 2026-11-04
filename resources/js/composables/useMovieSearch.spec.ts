import { describe, expect, it, vi } from 'vitest';
import { ApiError, type searchMovies } from '../api/client';
import type { MovieSummary, SearchResponse } from '../types/movie';
import { useMovieSearch } from './useMovieSearch';

type Search = typeof searchMovies;

function movie(title: string): MovieSummary {
    return { imdb_id: 'tt0000001', title, year: '2005', type: 'movie', poster: null };
}

function response(titles: string[], page = 1): SearchResponse {
    return {
        data: titles.map(movie),
        meta: { page, total: titles.length, per_page: 10, last_page: 1 },
    };
}

function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>((r) => {
        resolve = r;
    });

    return { promise, resolve };
}

describe('useMovieSearch', () => {
    it('starts idle', () => {
        const { state } = useMovieSearch(vi.fn<Search>());

        expect(state.value).toEqual({ status: 'idle' });
    });

    it('is loading while the request is running, then shows the movies', async () => {
        const pending = deferred<SearchResponse>();
        const { state, submit } = useMovieSearch(vi.fn<Search>().mockReturnValue(pending.promise));

        const running = submit('batman');
        expect(state.value).toEqual({ status: 'loading' });

        pending.resolve(response(['Batman Begins']));
        await running;

        expect(state.value).toEqual({
            status: 'success',
            movies: [movie('Batman Begins')],
            meta: { page: 1, total: 1, per_page: 10, last_page: 1 },
        });
    });

    it('trims the text before searching', async () => {
        const search = vi.fn<Search>().mockResolvedValue(response(['Batman Begins']));
        const { submit, query } = useMovieSearch(search);

        await submit('  batman ');

        expect(search).toHaveBeenCalledWith('batman', 1, expect.any(AbortSignal));
        expect(query.value).toBe('batman');
    });

    it('ignores blank text', async () => {
        const search = vi.fn<Search>();
        const { state, submit } = useMovieSearch(search);

        await submit('   ');

        expect(search).not.toHaveBeenCalled();
        expect(state.value).toEqual({ status: 'idle' });
    });

    it('is empty when nothing matches', async () => {
        const { state, submit } = useMovieSearch(vi.fn<Search>().mockResolvedValue(response([])));

        await submit('zzzzqqqxxyy');

        expect(state.value).toEqual({ status: 'empty' });
    });

    it('shows the message of an API error', async () => {
        const failing = vi.fn<Search>().mockRejectedValue(new ApiError('Too many results.', 422));
        const { state, submit } = useMovieSearch(failing);

        await submit('the');

        expect(state.value).toEqual({ status: 'error', message: 'Too many results.' });
    });

    it('falls back to a generic message for unexpected errors', async () => {
        const failing = vi.fn<Search>().mockRejectedValue('boom');
        const { state, submit } = useMovieSearch(failing);

        await submit('batman');

        expect(state.value).toEqual({ status: 'error', message: 'Something went wrong.' });
    });

    it('goToPage repeats the same search on another page', async () => {
        const search = vi.fn<Search>().mockResolvedValue(response(['Batman Begins']));
        const { submit, goToPage, page } = useMovieSearch(search);

        await submit('batman');
        await goToPage(3);

        expect(search).toHaveBeenLastCalledWith('batman', 3, expect.any(AbortSignal));
        expect(page.value).toBe(3);
    });

    it('a newer search cancels the older one and the old answer is ignored', async () => {
        const slow = deferred<SearchResponse>();
        const search = vi
            .fn<Search>()
            .mockReturnValueOnce(slow.promise)
            .mockResolvedValueOnce(response(['The Matrix']));
        const { state, submit } = useMovieSearch(search);

        const first = submit('batman');
        await submit('matrix');

        slow.resolve(response(['Batman Begins']));
        await first;

        expect(search.mock.calls[0]?.[2]?.aborted).toBe(true);
        expect(state.value).toMatchObject({ status: 'success', movies: [movie('The Matrix')] });
    });
});
