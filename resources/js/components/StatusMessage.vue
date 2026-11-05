<script setup lang="ts">
import { computed } from 'vue';
import type { SearchState } from '../composables/useMovieSearch';

const props = defineProps<{ state: SearchState; query: string }>();

const message = computed(() => {
    switch (props.state.status) {
        case 'idle':
            return 'Type a movie title and press Search.';
        case 'loading':
            return 'Searching…';
        case 'empty':
            return `No movies found for “${props.query}”. Try another title.`;
        case 'error':
            return props.state.message;
        default:
            return '';
    }
});
</script>

<template>
    <!-- The region stays in the page, so screen readers announce text as it changes. -->
    <div role="status" aria-live="polite">
        <p v-if="message" :class="['mt-8 text-center', state.status === 'error' ? 'text-red-700' : 'text-gray-600']">
            {{ message }}
        </p>
    </div>
</template>
