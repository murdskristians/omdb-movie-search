import { describe, expect, it, vi } from 'vitest';
import { ApiError, type getMovie } from '../api/client';
import type { MovieDetails } from '../types/movie';
import { useMovieDetails } from './useMovieDetails';

type Fetch = typeof getMovie;

function movie(title: string): MovieDetails {
    return {
        imdb_id: 'tt0372784',
        title,
        year: '2005',
        type: 'movie',
        rated: 'PG-13',
        runtime: '140 min',
        genres: ['Crime'],
        plot: 'A plot.',
        director: 'Christopher Nolan',
        writers: [],
        actors: [],
        awards: null,
        poster: null,
        ratings: [],
        imdb_rating: 8.2,
        imdb_votes: 1748238,
    };
}

function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>((r) => {
        resolve = r;
    });

    return { promise, resolve };
}

describe('useMovieDetails', () => {
    it('starts closed', () => {
        const { state } = useMovieDetails(vi.fn<Fetch>());

        expect(state.value).toEqual({ status: 'idle' });
    });

    it('is loading while the request runs, then shows the movie', async () => {
        const pending = deferred<MovieDetails>();
        const fetchMovie = vi.fn<Fetch>().mockReturnValue(pending.promise);
        const { state, open } = useMovieDetails(fetchMovie);

        const running = open('tt0372784');
        expect(state.value).toEqual({ status: 'loading' });
        expect(fetchMovie).toHaveBeenCalledWith('tt0372784', expect.any(AbortSignal));

        pending.resolve(movie('Batman Begins'));
        await running;

        expect(state.value).toEqual({ status: 'success', movie: movie('Batman Begins') });
    });

    it('shows the message of an API error', async () => {
        const failing = vi.fn<Fetch>().mockRejectedValue(new ApiError('Movie not found.', 404));
        const { state, open } = useMovieDetails(failing);

        await open('tt9999999');

        expect(state.value).toEqual({ status: 'error', message: 'Movie not found.' });
    });

    it('falls back to a generic message for unexpected errors', async () => {
        const failing = vi.fn<Fetch>().mockRejectedValue('boom');
        const { state, open } = useMovieDetails(failing);

        await open('tt0372784');

        expect(state.value).toEqual({ status: 'error', message: 'Something went wrong.' });
    });

    it('close goes back to idle', async () => {
        const { state, open, close } = useMovieDetails(vi.fn<Fetch>().mockResolvedValue(movie('Batman Begins')));
        await open('tt0372784');

        close();

        expect(state.value).toEqual({ status: 'idle' });
    });

    it('an answer that arrives after close does not reopen the window', async () => {
        const slow = deferred<MovieDetails>();
        const fetchMovie = vi.fn<Fetch>().mockReturnValue(slow.promise);
        const { state, open, close } = useMovieDetails(fetchMovie);

        const running = open('tt0372784');
        close();
        slow.resolve(movie('Batman Begins'));
        await running;

        expect(fetchMovie.mock.calls[0]?.[1]?.aborted).toBe(true);
        expect(state.value).toEqual({ status: 'idle' });
    });

    it('opening another movie cancels the older request and ignores its answer', async () => {
        const slow = deferred<MovieDetails>();
        const fetchMovie = vi
            .fn<Fetch>()
            .mockReturnValueOnce(slow.promise)
            .mockResolvedValueOnce(movie('The Dark Knight'));
        const { state, open } = useMovieDetails(fetchMovie);

        const first = open('tt0372784');
        await open('tt0468569');

        slow.resolve(movie('Batman Begins'));
        await first;

        expect(fetchMovie.mock.calls[0]?.[1]?.aborted).toBe(true);
        expect(state.value).toEqual({ status: 'success', movie: movie('The Dark Knight') });
    });

    it('a cancelled request that fails does not show an error', async () => {
        const slow = deferred<MovieDetails>();
        const fetchMovie = vi.fn<Fetch>().mockImplementation(
            () =>
                new Promise<MovieDetails>((_, reject) => {
                    slow.promise.then(() => reject(new Error('cancelled')));
                }),
        );
        const { state, open, close } = useMovieDetails(fetchMovie);

        const running = open('tt0372784');
        close();
        slow.resolve(movie('x'));
        await running;

        expect(state.value).toEqual({ status: 'idle' });
    });
});
