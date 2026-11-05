<script setup lang="ts">
import { ref } from 'vue';

defineProps<{ disabled?: boolean }>();

const emit = defineEmits<{ search: [text: string] }>();

const text = ref('');

function onSubmit(): void {
    const trimmed = text.value.trim();

    if (trimmed === '') {
        return;
    }

    emit('search', trimmed);
}
</script>

<template>
    <form class="flex gap-2" role="search" @submit.prevent="onSubmit">
        <label class="sr-only" for="movie-title">Movie title</label>
        <input
            id="movie-title"
            v-model="text"
            type="search"
            maxlength="100"
            autocomplete="off"
            placeholder="Search movies by title"
            class="min-h-11 min-w-0 flex-1 rounded border border-gray-300 px-3"
        />
        <button
            type="submit"
            :disabled="disabled || text.trim() === ''"
            class="min-h-11 rounded bg-gray-900 px-4 text-white disabled:opacity-50"
        >
            Search
        </button>
    </form>
</template>
