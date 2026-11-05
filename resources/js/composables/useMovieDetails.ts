import { ref } from 'vue';
import { getMovie } from '../api/client';
import type { MovieDetails } from '../types/movie';

export type DetailsState =
    | { status: 'idle' }
    | { status: 'loading' }
    | { status: 'success'; movie: MovieDetails }
    | { status: 'error'; message: string };

export function useMovieDetails(fetchMovie: typeof getMovie = getMovie) {
    const state = ref<DetailsState>({ status: 'idle' });

    let controller: AbortController | null = null;

    async function open(imdbId: string): Promise<void> {
        // Only the movie asked for last may fill the window, so stop the previous request.
        controller?.abort();
        const current = new AbortController();
        controller = current;

        state.value = { status: 'loading' };

        try {
            const movie = await fetchMovie(imdbId, current.signal);

            if (current.signal.aborted) {
                return;
            }

            state.value = { status: 'success', movie };
        } catch (error) {
            if (current.signal.aborted) {
                return;
            }

            state.value = {
                status: 'error',
                message: error instanceof Error ? error.message : 'Something went wrong.',
            };
        }
    }

    function close(): void {
        controller?.abort();
        controller = null;
        state.value = { status: 'idle' };
    }

    return { state, open, close };
}
