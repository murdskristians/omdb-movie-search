import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { MovieSummary } from '../types/movie';
import MovieCard from './MovieCard.vue';

const movie: MovieSummary = {
    imdb_id: 'tt0372784',
    title: 'Batman Begins',
    year: '2005',
    type: 'movie',
    poster: null,
};

describe('MovieCard', () => {
    it('shows the title, the year and the type', () => {
        const text = mount(MovieCard, { props: { movie } }).text();

        expect(text).toContain('Batman Begins');
        expect(text).toContain('2005 · movie');
    });

    it('emits the id of the movie when the title button is clicked', async () => {
        const wrapper = mount(MovieCard, { props: { movie } });

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('select')).toEqual([['tt0372784']]);
    });
});
