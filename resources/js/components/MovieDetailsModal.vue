<script setup lang="ts">
import type { DetailsState } from '../composables/useMovieDetails';
import type { MovieDetails } from '../types/movie';
import PosterImage from './PosterImage.vue';

defineProps<{ state: DetailsState }>();

const emit = defineEmits<{ close: [] }>();

// The IMDb rating has its own line, so the list of other ratings leaves it out.
const IMDB_SOURCE = 'Internet Movie Database';

function summaryLine(movie: MovieDetails): string {
    return [movie.year, movie.rated, movie.runtime].filter(Boolean).join(' · ');
}

function otherRatings(movie: MovieDetails) {
    return movie.ratings.filter((rating) => rating.source !== IMDB_SOURCE);
}

function formatVotes(votes: number): string {
    return votes.toLocaleString('en-US');
}
</script>

<template>
    <div v-if="state.status !== 'idle'" class="fixed inset-0 z-10 flex items-center justify-center bg-black/60 p-4">
        <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-6">
            <div class="mb-4 flex justify-end">
                <button type="button" class="rounded border border-gray-300 px-3 py-1" @click="emit('close')">
                    Close
                </button>
            </div>

            <p v-if="state.status === 'loading'" class="py-8 text-center text-gray-600">Loading…</p>

            <p v-else-if="state.status === 'error'" class="py-8 text-center text-red-700">{{ state.message }}</p>

            <div v-else-if="state.status === 'success'" class="grid gap-6 sm:grid-cols-[12rem_1fr]">
                <PosterImage :src="state.movie.poster" :alt="state.movie.title" />

                <div>
                    <h2 class="text-2xl font-bold">{{ state.movie.title }}</h2>
                    <p class="text-gray-600">{{ summaryLine(state.movie) }}</p>

                    <ul v-if="state.movie.genres.length > 0" class="mt-3 flex flex-wrap gap-2">
                        <li v-for="genre in state.movie.genres" :key="genre" class="rounded-full bg-gray-100 px-3 py-1 text-sm">
                            {{ genre }}
                        </li>
                    </ul>

                    <p class="mt-4">
                        <template v-if="state.movie.imdb_rating !== null">
                            IMDb rating: <strong>{{ state.movie.imdb_rating }}</strong>/10
                            <span v-if="state.movie.imdb_votes !== null" class="text-gray-600">
                                ({{ formatVotes(state.movie.imdb_votes) }} votes)
                            </span>
                        </template>
                        <template v-else>No IMDb rating yet</template>
                    </p>

                    <ul v-if="otherRatings(state.movie).length > 0" class="mt-1 text-sm text-gray-600">
                        <li v-for="rating in otherRatings(state.movie)" :key="rating.source">
                            {{ rating.source }}: {{ rating.value }}
                        </li>
                    </ul>

                    <p v-if="state.movie.plot" class="mt-4">{{ state.movie.plot }}</p>

                    <dl class="mt-4 space-y-1 text-sm">
                        <div v-if="state.movie.director">
                            <dt class="inline font-semibold">Director:</dt>
                            <dd class="ml-1 inline">{{ state.movie.director }}</dd>
                        </div>
                        <div v-if="state.movie.writers.length > 0">
                            <dt class="inline font-semibold">Writers:</dt>
                            <dd class="ml-1 inline">{{ state.movie.writers.join(', ') }}</dd>
                        </div>
                        <div v-if="state.movie.actors.length > 0">
                            <dt class="inline font-semibold">Actors:</dt>
                            <dd class="ml-1 inline">{{ state.movie.actors.join(', ') }}</dd>
                        </div>
                        <div v-if="state.movie.awards">
                            <dt class="inline font-semibold">Awards:</dt>
                            <dd class="ml-1 inline">{{ state.movie.awards }}</dd>
                        </div>
                    </dl>

                    <a
                        :href="`https://www.imdb.com/title/${state.movie.imdb_id}/`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-4 inline-block text-sm underline"
                    >
                        View on IMDb
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
