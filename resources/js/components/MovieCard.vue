<script setup lang="ts">
import type { MovieSummary } from '../types/movie';
import PosterImage from './PosterImage.vue';

defineProps<{ movie: MovieSummary }>();
</script>

<template>
    <article class="flex flex-col gap-2">
        <PosterImage :src="movie.poster" :alt="movie.title" />
        <h2 class="font-semibold leading-tight">{{ movie.title }}</h2>
        <p class="text-sm text-gray-600">{{ movie.year }} · {{ movie.type }}</p>
    </article>
</template>
