<script setup lang="ts">
import SearchForm from './components/SearchForm.vue';
import { useMovieSearch } from './composables/useMovieSearch';

const appName = 'OMDb Movie Search';

const { state, submit } = useMovieSearch();
</script>

<template>
    <main class="mx-auto max-w-4xl px-4 py-8">
        <h1 class="mb-6 text-3xl font-bold">{{ appName }}</h1>
        <SearchForm :disabled="state.status === 'loading'" @search="submit" />
    </main>
</template>
