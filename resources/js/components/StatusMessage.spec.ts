import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { SearchState } from '../composables/useMovieSearch';
import StatusMessage from './StatusMessage.vue';

function show(state: SearchState, query = '') {
    return mount(StatusMessage, { props: { state, query } });
}

describe('StatusMessage', () => {
    it('hints what to do before the first search', () => {
        expect(show({ status: 'idle' }).text()).toBe('Type a movie title and press Search.');
    });

    it('says that it is searching while loading', () => {
        expect(show({ status: 'loading' }).text()).toBe('Searching…');
    });

    it('names the title when nothing was found', () => {
        expect(show({ status: 'empty' }, 'zzzz').text()).toContain('No movies found for “zzzz”.');
    });

    it('shows the error message as it came from the server', () => {
        const wrapper = show({ status: 'error', message: 'Too many results. Try a more specific title.' });

        expect(wrapper.text()).toBe('Too many results. Try a more specific title.');
        expect(wrapper.find('p').classes()).toContain('text-red-700');
    });

    it('shows no text on success', () => {
        const wrapper = show({
            status: 'success',
            movies: [],
            meta: { page: 1, total: 0, per_page: 10, last_page: 1 },
        });

        expect(wrapper.text()).toBe('');
    });

    it('keeps the live region in the page even when there is nothing to say', () => {
        const wrapper = show({ status: 'success', movies: [], meta: { page: 1, total: 0, per_page: 10, last_page: 1 } });

        expect(wrapper.find('[aria-live="polite"]').exists()).toBe(true);
    });
});
