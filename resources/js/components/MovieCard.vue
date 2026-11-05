<script setup lang="ts">
import type { MovieSummary } from '../types/movie';
import PosterImage from './PosterImage.vue';

defineProps<{ movie: MovieSummary }>();

const emit = defineEmits<{ select: [imdbId: string] }>();
</script>

<template>
    <article class="relative flex flex-col gap-2">
        <PosterImage :src="movie.poster" :alt="movie.title" />
        <h2 class="font-semibold leading-tight">
            <!-- The ::after layer stretches this one real button over the whole card. -->
            <button
                type="button"
                class="text-left after:absolute after:inset-0 after:rounded focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-gray-900"
                @click="emit('select', movie.imdb_id)"
            >
                {{ movie.title }}
            </button>
        </h2>
        <p class="text-sm text-gray-600">{{ movie.year }} · {{ movie.type }}</p>
    </article>
</template>
