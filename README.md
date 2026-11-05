# OMDb Movie Search

Search movies by title, browse the results, and open the details of a movie. The app remembers your last five searches.

Laravel 12 (PHP 8.2) serves a JSON API in front of the [OMDb API](https://www.omdbapi.com/). A Vue 3 + TypeScript page talks only to that API, never to OMDb directly.

![Search results for "batman"](docs/screenshot.png)

## Features

- Search by title, with paging (OMDb returns 10 results per page).
- Results as cards with poster, title, year and type. A placeholder is shown when a movie has no poster or the image fails to load.
- Details window: poster, plot, IMDb rating with votes, other ratings, genres, director, writers, actors, runtime, awards and a link to IMDb.
- The last five searches are kept (newest first, no duplicates, survives a page reload). Click one to run it again, or clear the list.
- Clear messages for the start screen, loading, no results, "too many results", and service errors.
- Keyboard friendly: the details window closes with Esc, keeps Tab inside, and returns focus to the card. The layout works down to a 360 px wide screen.

## Requirements

- PHP 8.2 or newer and [Composer](https://getcomposer.org/)
- Node.js 22.12 or newer (Vitest 5 asks for it; Vite 7 alone needs 20.19+)
- A free OMDb API key: <https://www.omdbapi.com/apikey.aspx>

SQLite is used for sessions and the cache, so no database server is needed.

## Setup

```bash
git clone <repository-url>
cd omdb-movie-search

composer install
cp .env.example .env            # Windows cmd: copy .env.example .env
php artisan key:generate        # creates Laravel's own APP_KEY in .env (see below)

php -r "touch('database/database.sqlite');"   # creates the empty SQLite file
php artisan migrate

npm install
```

Then open `.env` and set your OMDb key by hand:

```
OMDB_API_KEY=your-key
```

There are two different keys, and only one of them is generated for you:

- `APP_KEY` is Laravel's secret for encrypting cookies and sessions. `php artisan key:generate` creates it.
- `OMDB_API_KEY` is your access key for OMDb. Nothing can generate it: request a free key at <https://www.omdbapi.com/apikey.aspx>, confirm it by email, and paste it into `.env`. Without it every search fails with "The movie service is not configured correctly."

`php artisan migrate` stops with an error if the SQLite file does not exist yet, so create it first.

## Run it

Use two terminals:

```bash
php artisan serve     # the app and the API, http://localhost:8000
npm run dev           # Vite, serves the frontend files with hot reload
```

Open **http://localhost:8000** (not the Vite address, `localhost:5173`).

Without `npm run dev` you can build the frontend once with `npm run build` and run only `php artisan serve`. Remember to run the build again after you change the frontend, because the built files are not updated by themselves.

## Tests and checks

| Command | What it does |
|---------|--------------|
| `php artisan test` | PHP unit and feature tests |
| `npm test` | Vue component, composable and App tests (Vitest, jsdom) |
| `npm run typecheck` | TypeScript check of the whole frontend (`vue-tsc`) |
| `npm run lint` | ESLint |
| `npm run format:check` | Prettier check (`npm run format` fixes files) |
| `./vendor/bin/pint --test` | PHP code style check (without `--test` it fixes files) |

No test calls the real OMDb. PHP tests use `Http::fake()`, and the frontend tests replace the network with a fake API.

## API

All routes are under `/api`.

| Method and path | Description |
|-----------------|-------------|
| `GET /api/movies?q=batman&page=1` | Search by title. `q` is required (max 100 characters), `page` is 1 to 100. Returns `data` (movies) and `meta` (`page`, `total`, `per_page`, `last_page`). A successful search with results on page 1 is added to the history. |
| `GET /api/movies/{imdbId}` | Details of one movie. The id looks like `tt0372784`; anything else is a 404 that never reaches OMDb. |
| `GET /api/history` | The last five search texts of this browser session, newest first. |
| `DELETE /api/history` | Clears that list. |

Errors are JSON, `{"message": "..."}`, with a fitting status: 404 not found, 422 invalid input or too many results, 429 request limit reached, 502 OMDb not reachable, 500 missing or invalid API key.

## How it is built

```
Vue 3 page  --->  Laravel API  --->  OMDb
 (browser)       (OmdbClient)
                 (SearchHistory, session)
```

```
app/Services/Omdb/            OmdbClient, OmdbException, OmdbErrorKind
app/Services/SearchHistory.php
app/Http/Controllers/Api/     MovieController, HistoryController
app/Http/Requests/            SearchMovieRequest (validation)
app/Http/Resources/           MovieSummaryResource, MovieDetailsResource (the JSON shape)
routes/api.php, routes/web.php
resources/js/
    api/client.ts             the only code that sends requests
    composables/              useMovieSearch, useMovieDetails, useSearchHistory
    components/               SearchForm, MovieList, MovieCard, PosterImage, PaginationNav,
                              StatusMessage, SearchHistory, MovieDetailsModal
    types/movie.ts            the shape of the API answers
```

## Decisions and trade-offs

- **Laravel sits between the browser and OMDb.** The API key never reaches the browser, OMDb's odd answers are cleaned up in one place (most errors arrive as HTTP 200 with `"Response": "False"`, missing values are the text `"N/A"`, lists are one comma-separated string, votes contain commas), and the frontend gets stable, typed JSON.
- **Caching.** Search answers are cached for 10 minutes and movie details for 24 hours, so repeated requests do not use up the daily OMDb limit. Failures are not cached, so a short outage does not stick. An empty search result is cached.
- **Fail fast, retry a little.** Requests to OMDb have a 5 second timeout and are retried only after network errors and 5xx answers, never after a plain "not found".
- **History lives in the session.** No accounts and no table of their own, which fits a small app. The price: it belongs to one browser, and it disappears when the session cookie is cleared or expires (`SESSION_LIFETIME` is 30 days in `.env.example`). The history routes and the search route run in Laravel's `web` middleware group, because that is what starts the session. Because `DELETE` changes data, it needs the CSRF token; Axios sends it from the `XSRF-TOKEN` cookie.
- **What counts as a search in the history:** the first page of a search that found at least one movie. Paging, empty results and errors are not recorded. Recording happens in the controller, so answers served from the cache count too.
- **Stale answers are ignored on the frontend.** Starting a new search or opening another movie cancels the previous request (`AbortController`), so a slow old answer cannot overwrite a newer one.
- **Composables take their dependencies as parameters.** Tests pass in fakes, so there is no need for module mocking.
- **Paging stops at page 100,** which is what the API accepts.

## Known limitations

- A movie that OMDb sends with an empty or `"N/A"` title or id would still be shown as it is. Entries with no title or id at all are skipped in search results, and the details endpoint answers 502 for them.
- OMDb answers `"Error getting data."` for an id that does not exist. The app treats that as "not found", so a genuine glitch with the same text would also look like a 404.
- Search is by title only. OMDb can also filter by year and type; this app does not use that.
- The frontend tests run in jsdom, not in a real browser. The layout was checked by hand in Chrome at 360 px and 1280 px, and nowhere else.
- Returning focus to the card after closing the details window relies on the clicked button having focus. Some browsers (Safari, as far as I know) do not focus buttons on click, so there the focus would not return. Keyboard use is not affected.
- `npm audit` reports moderate advisories in development tools (`minimatch`, used by ESLint and the test tooling). They do not ship with the app.

## What could come next

- Filters for year and type, and a link that opens the details of a movie directly.
- Browser end-to-end tests, for example with Playwright.
- A GitHub Actions workflow that runs the checks above on every push.
- Docker or Sail for a one-command setup.
