import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { AxiosError, type AxiosAdapter, type InternalAxiosRequestConfig } from 'axios';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import App from './App.vue';
import { http } from './api/client';
import type { MovieDetails, MovieSummary } from './types/movie';

const originalAdapter = http.defaults.adapter;

const begins: MovieSummary = { imdb_id: 'tt0372784', title: 'Batman Begins', year: '2005', type: 'movie', poster: null };
const darkKnight: MovieSummary = { imdb_id: 'tt0468569', title: 'The Dark Knight', year: '2008', type: 'movie', poster: null };

const beginsDetails: MovieDetails = {
    ...begins,
    rated: 'PG-13',
    runtime: '140 min',
    genres: ['Action', 'Crime'],
    plot: 'Billionaire Bruce Wayne becomes Batman.',
    director: 'Christopher Nolan',
    writers: ['Bob Kane'],
    actors: ['Christian Bale'],
    awards: null,
    ratings: [{ source: 'Internet Movie Database', value: '8.2/10' }],
    imdb_rating: 8.2,
    imdb_votes: 1748238,
};

/**
 * A tiny stand-in for the Laravel API. It answers like the real one and, like the real one,
 * remembers the searches that found something.
 */
class FakeApi {
    history: string[];
    requests: string[] = [];

    constructor(history: string[] = []) {
        this.history = history;
    }

    adapter: AxiosAdapter = (config) => {
        const method = (config.method ?? 'get').toUpperCase();
        const url = config.url ?? '';
        this.requests.push(`${method} ${url}${config.params ? `?q=${config.params.q}&page=${config.params.page}` : ''}`);

        const [status, data] = this.answer(method, url, config);
        const response = { data, status, statusText: '', headers: {}, config };

        return status < 400
            ? Promise.resolve(response)
            : Promise.reject(new AxiosError('Request failed', 'ERR_BAD_REQUEST', config, null, response));
    };

    private answer(method: string, url: string, config: InternalAxiosRequestConfig): [number, unknown] {
        if (url === '/history') {
            if (method === 'DELETE') {
                this.history = [];

                return [204, ''];
            }

            return [200, { data: this.history }];
        }

        if (url === '/movies') {
            const { q, page } = config.params as { q: string; page: number };

            if (q === 'the') {
                return [422, { message: 'Too many results. Try a more specific title.' }];
            }

            if (q === 'zzzz') {
                return [200, { data: [], meta: { page: 1, total: 0, per_page: 10, last_page: 1 } }];
            }

            if (page === 1) {
                this.history = [q, ...this.history.filter((saved) => saved !== q)].slice(0, 5);
            }

            return [200, { data: [begins, darkKnight], meta: { page, total: 25, per_page: 10, last_page: 3 } }];
        }

        if (url === `/movies/${begins.imdb_id}`) {
            return [200, { data: beginsDetails }];
        }

        return [404, { message: 'Movie not found.' }];
    }
}

const mounted: VueWrapper[] = [];

async function openApp(api: FakeApi): Promise<VueWrapper> {
    http.defaults.adapter = api.adapter;
    const wrapper = mount(App, { attachTo: document.body });
    mounted.push(wrapper);
    await flushPromises();

    return wrapper;
}

async function search(wrapper: VueWrapper, text: string): Promise<void> {
    await wrapper.find('input').setValue(text);
    await wrapper.find('form').trigger('submit');
    await flushPromises();
}

function buttonWithText(wrapper: VueWrapper, text: string) {
    const button = wrapper.findAll('button').find((candidate) => candidate.text() === text);

    if (!button) {
        throw new Error(`No button with the text "${text}".`);
    }

    return button;
}

const titles = (wrapper: VueWrapper) => wrapper.findAll('article h2').map((title) => title.text());
const chips = (wrapper: VueWrapper) => wrapper.findAll('section[aria-label="Recent searches"] li').map((chip) => chip.text());
const status = (wrapper: VueWrapper) => wrapper.find('[role="status"]').text();

describe('App', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    afterEach(() => {
        mounted.splice(0).forEach((wrapper) => wrapper.unmount());
        http.defaults.adapter = originalAdapter;
    });

    it('starts with the heading, a hint and no results', async () => {
        const wrapper = await openApp(new FakeApi());

        expect(wrapper.find('h1').text()).toBe('OMDb Movie Search');
        expect(status(wrapper)).toBe('Type a movie title and press Search.');
        expect(titles(wrapper)).toEqual([]);
        expect(chips(wrapper)).toEqual([]);
    });

    it('shows the searches saved earlier as soon as the page opens', async () => {
        const wrapper = await openApp(new FakeApi(['matrix', 'batman']));

        expect(chips(wrapper)).toEqual(['matrix', 'batman']);
    });

    it('searches, shows the movies and adds the search to the recent searches', async () => {
        const api = new FakeApi();
        const wrapper = await openApp(api);

        await search(wrapper, '  batman ');

        expect(api.requests).toContain('GET /movies?q=batman&page=1');
        expect(titles(wrapper)).toEqual(['Batman Begins', 'The Dark Knight']);
        expect(chips(wrapper)).toEqual(['batman']);
        expect(wrapper.text()).toContain('Page 1 of 3');
    });

    it('runs the search again when a recent search is clicked', async () => {
        const api = new FakeApi(['matrix', 'batman']);
        const wrapper = await openApp(api);

        await wrapper.findAll('section[aria-label="Recent searches"] li button')[1]!.trigger('click');
        await flushPromises();

        expect(api.requests).toContain('GET /movies?q=batman&page=1');
        expect(titles(wrapper)).toEqual(['Batman Begins', 'The Dark Knight']);
        expect(chips(wrapper)).toEqual(['batman', 'matrix']);
    });

    it('goes to the next page', async () => {
        const api = new FakeApi();
        const wrapper = await openApp(api);
        await search(wrapper, 'batman');

        await buttonWithText(wrapper, 'Next').trigger('click');
        await flushPromises();

        expect(api.requests).toContain('GET /movies?q=batman&page=2');
        expect(wrapper.text()).toContain('Page 2 of 3');
        expect(chips(wrapper)).toEqual(['batman']);
    });

    it('says so when nothing was found, and does not remember that search', async () => {
        const wrapper = await openApp(new FakeApi());

        await search(wrapper, 'zzzz');

        expect(status(wrapper)).toContain('No movies found for “zzzz”.');
        expect(chips(wrapper)).toEqual([]);
    });

    it('shows the message of the server when the search fails', async () => {
        const wrapper = await openApp(new FakeApi());

        await search(wrapper, 'the');

        expect(status(wrapper)).toBe('Too many results. Try a more specific title.');
        expect(titles(wrapper)).toEqual([]);
        expect(chips(wrapper)).toEqual([]);
    });

    it('opens the details of a movie and closes them again', async () => {
        const api = new FakeApi();
        const wrapper = await openApp(api);
        await search(wrapper, 'batman');

        await buttonWithText(wrapper, 'Batman Begins').trigger('click');
        await flushPromises();

        const dialog = wrapper.find('[role="dialog"]');
        expect(api.requests).toContain('GET /movies/tt0372784');
        expect(dialog.text()).toContain('Billionaire Bruce Wayne becomes Batman.');
        expect(dialog.text()).toContain('1,748,238 votes');

        await buttonWithText(wrapper, 'Close').trigger('click');

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(titles(wrapper)).toEqual(['Batman Begins', 'The Dark Knight']);
    });

    it('closes the details with the Escape key', async () => {
        const wrapper = await openApp(new FakeApi());
        await search(wrapper, 'batman');
        await buttonWithText(wrapper, 'Batman Begins').trigger('click');
        await flushPromises();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flushPromises();

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('shows an error inside the details window when the movie cannot be loaded', async () => {
        const wrapper = await openApp(new FakeApi());
        await search(wrapper, 'batman');

        await buttonWithText(wrapper, 'The Dark Knight').trigger('click');
        await flushPromises();

        expect(wrapper.find('[role="dialog"]').text()).toContain('Movie not found.');
    });

    it('clears the recent searches', async () => {
        const api = new FakeApi(['matrix', 'batman']);
        const wrapper = await openApp(api);

        await buttonWithText(wrapper, 'Clear').trigger('click');
        await flushPromises();

        expect(api.requests).toContain('DELETE /history');
        expect(chips(wrapper)).toEqual([]);
    });
});
