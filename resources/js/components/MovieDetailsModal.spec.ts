import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { DetailsState } from '../composables/useMovieDetails';
import type { MovieDetails } from '../types/movie';
import MovieDetailsModal from './MovieDetailsModal.vue';

function movie(overrides: Partial<MovieDetails> = {}): MovieDetails {
    return {
        imdb_id: 'tt0372784',
        title: 'Batman Begins',
        year: '2005',
        type: 'movie',
        rated: 'PG-13',
        runtime: '140 min',
        genres: ['Action', 'Crime'],
        plot: 'Billionaire Bruce Wayne becomes Batman.',
        director: 'Christopher Nolan',
        writers: ['Bob Kane', 'David S. Goyer'],
        actors: ['Christian Bale', 'Michael Caine'],
        awards: 'Nominated for 1 Oscar.',
        poster: 'https://example.test/begins.jpg',
        ratings: [
            { source: 'Internet Movie Database', value: '8.2/10' },
            { source: 'Rotten Tomatoes', value: '85%' },
        ],
        imdb_rating: 8.2,
        imdb_votes: 1748238,
        ...overrides,
    };
}

function show(state: DetailsState) {
    return mount(MovieDetailsModal, { props: { state } });
}

describe('MovieDetailsModal', () => {
    it('shows nothing while closed', () => {
        expect(show({ status: 'idle' }).html()).toBe('<!--v-if-->');
    });

    it('says it is loading', () => {
        expect(show({ status: 'loading' }).text()).toContain('Loading…');
    });

    it('shows the error message and still offers Close', () => {
        const wrapper = show({ status: 'error', message: 'Movie not found.' });

        expect(wrapper.text()).toContain('Movie not found.');
        expect(wrapper.find('button').text()).toBe('Close');
    });

    it('shows the title, the facts line, the genres and the plot', () => {
        const text = show({ status: 'success', movie: movie() }).text();

        expect(text).toContain('Batman Begins');
        expect(text).toContain('2005 · PG-13 · 140 min');
        expect(text).toContain('Action');
        expect(text).toContain('Crime');
        expect(text).toContain('Billionaire Bruce Wayne becomes Batman.');
    });

    it('shows the people and the awards', () => {
        const wrapper = show({ status: 'success', movie: movie() });
        const facts = Object.fromEntries(
            wrapper.findAll('dl > div').map((row) => [row.find('dt').text(), row.find('dd').text()]),
        );

        expect(facts).toEqual({
            'Director:': 'Christopher Nolan',
            'Writers:': 'Bob Kane, David S. Goyer',
            'Actors:': 'Christian Bale, Michael Caine',
            'Awards:': 'Nominated for 1 Oscar.',
        });
    });

    it('shows the IMDb rating with the votes written with separators', () => {
        const text = show({ status: 'success', movie: movie() }).text();

        expect(text).toContain('IMDb rating: 8.2/10');
        expect(text).toContain('(1,748,238 votes)');
    });

    it('lists the other ratings but not the IMDb one a second time', () => {
        const wrapper = show({ status: 'success', movie: movie() });
        const items = wrapper.findAll('ul')[1]!.findAll('li').map((li) => li.text());

        expect(items).toEqual(['Rotten Tomatoes: 85%']);
    });

    it('links to the movie on IMDb in a new tab', () => {
        const link = show({ status: 'success', movie: movie() }).find('a');

        expect(link.attributes('href')).toBe('https://www.imdb.com/title/tt0372784/');
        expect(link.attributes('target')).toBe('_blank');
        expect(link.attributes('rel')).toContain('noopener');
    });

    it('leaves out the lines OMDb has no value for', () => {
        const wrapper = show({
            status: 'success',
            movie: movie({
                rated: null,
                runtime: null,
                genres: [],
                plot: null,
                director: null,
                writers: [],
                actors: [],
                awards: null,
                ratings: [],
                imdb_rating: null,
                imdb_votes: null,
            }),
        });
        const text = wrapper.text();

        expect(text).toContain('Batman Begins');
        expect(wrapper.findAll('p').filter((p) => p.text() === '')).toHaveLength(0);
        expect(wrapper.findAll('li')).toHaveLength(0);
        expect(text).toContain('No IMDb rating yet');
        expect(text).not.toContain('Director');
        expect(text).not.toContain('Writers');
        expect(text).not.toContain('Actors');
        expect(text).not.toContain('Awards');
        expect(text).not.toContain('null');
    });

    it('emits close when Close is clicked', async () => {
        const wrapper = show({ status: 'success', movie: movie() });

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('close')).toHaveLength(1);
    });
});
