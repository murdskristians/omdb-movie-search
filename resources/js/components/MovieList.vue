<script setup lang="ts">
import type { MovieSummary } from '../types/movie';
import MovieCard from './MovieCard.vue';

defineProps<{ movies: MovieSummary[] }>();

const emit = defineEmits<{ select: [imdbId: string] }>();
</script>

<template>
    <ul class="grid grid-cols-2 gap-6 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
        <li v-for="movie in movies" :key="movie.imdb_id">
            <MovieCard :movie="movie" @select="emit('select', $event)" />
        </li>
    </ul>
</template>
