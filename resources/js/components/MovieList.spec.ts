import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { MovieSummary } from '../types/movie';
import MovieList from './MovieList.vue';

function movie(id: string, title: string, poster: string | null = null): MovieSummary {
    return { imdb_id: id, title, year: '2005', type: 'movie', poster };
}

describe('MovieList', () => {
    it('renders one card per movie with its title, year and type', () => {
        const wrapper = mount(MovieList, {
            props: { movies: [movie('tt1', 'Batman Begins'), movie('tt2', 'The Dark Knight')] },
        });

        const cards = wrapper.findAll('article');

        expect(cards).toHaveLength(2);
        expect(cards[0]?.text()).toContain('Batman Begins');
        expect(cards[0]?.text()).toContain('2005 · movie');
        expect(cards[1]?.text()).toContain('The Dark Knight');
    });

    it('passes on the id of the card that was clicked', async () => {
        const wrapper = mount(MovieList, {
            props: { movies: [movie('tt1', 'Batman Begins'), movie('tt2', 'The Dark Knight')] },
        });

        await wrapper.findAll('button')[1]!.trigger('click');

        expect(wrapper.emitted('select')).toEqual([['tt2']]);
    });

    it('shows a placeholder only for movies without a poster', () => {
        const wrapper = mount(MovieList, {
            props: {
                movies: [movie('tt1', 'With poster', 'https://example.test/a.jpg'), movie('tt2', 'Without poster')],
            },
        });

        expect(wrapper.findAll('img')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="poster-placeholder"]')).toHaveLength(1);
    });
});
