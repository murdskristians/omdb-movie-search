import { ref } from 'vue';
import { searchMovies } from '../api/client';
import type { MovieSummary, PageMeta } from '../types/movie';

export type SearchState =
    | { status: 'idle' }
    | { status: 'loading' }
    | { status: 'success'; movies: MovieSummary[]; meta: PageMeta }
    | { status: 'empty' }
    | { status: 'error'; message: string };

export function useMovieSearch(search: typeof searchMovies = searchMovies) {
    const state = ref<SearchState>({ status: 'idle' });
    const query = ref('');
    const page = ref(1);
    // Set when moving to another page fails. The results of the page we were on stay on screen.
    const pageError = ref<string | null>(null);

    let controller: AbortController | null = null;

    async function run(text: string, pageNumber: number, keepResultsOnError = false): Promise<void> {
        // Only the newest request may update the screen, so stop the previous one.
        controller?.abort();
        const current = new AbortController();
        controller = current;

        const previousState = state.value;
        const previousPage = page.value;

        query.value = text;
        page.value = pageNumber;
        pageError.value = null;
        state.value = { status: 'loading' };

        try {
            const { data, meta } = await search(text, pageNumber, current.signal);

            if (current.signal.aborted) {
                return;
            }

            state.value = data.length > 0 ? { status: 'success', movies: data, meta } : { status: 'empty' };
        } catch (error) {
            if (current.signal.aborted) {
                return;
            }

            const message = error instanceof Error ? error.message : 'Something went wrong.';

            if (keepResultsOnError && previousState.status === 'success') {
                state.value = previousState;
                page.value = previousPage;
                pageError.value = message;

                return;
            }

            state.value = { status: 'error', message };
        }
    }

    function submit(text: string): Promise<void> {
        const trimmed = text.trim();

        return trimmed === '' ? Promise.resolve() : run(trimmed, 1);
    }

    function goToPage(pageNumber: number): Promise<void> {
        return run(query.value, pageNumber, true);
    }

    return { state, query, page, pageError, submit, goToPage };
}
