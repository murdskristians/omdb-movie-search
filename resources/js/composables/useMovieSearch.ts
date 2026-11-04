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

    let controller: AbortController | null = null;

    async function run(text: string, pageNumber: number): Promise<void> {
        // Only the newest request may update the screen, so stop the previous one.
        controller?.abort();
        const current = new AbortController();
        controller = current;

        query.value = text;
        page.value = pageNumber;
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

            state.value = {
                status: 'error',
                message: error instanceof Error ? error.message : 'Something went wrong.',
            };
        }
    }

    function submit(text: string): Promise<void> {
        const trimmed = text.trim();

        return trimmed === '' ? Promise.resolve() : run(trimmed, 1);
    }

    function goToPage(pageNumber: number): Promise<void> {
        return run(query.value, pageNumber);
    }

    return { state, query, page, submit, goToPage };
}
