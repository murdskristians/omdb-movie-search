<script setup lang="ts">
import { computed } from 'vue';
import MovieList from './components/MovieList.vue';
import Pagination from './components/Pagination.vue';
import SearchForm from './components/SearchForm.vue';
import StatusMessage from './components/StatusMessage.vue';
import { useMovieSearch } from './composables/useMovieSearch';

const appName = 'OMDb Movie Search';

// The backend (and OMDb) accept pages 1 to 100 only, even if a search matches more movies.
const MAX_PAGE = 100;

const { state, query, page, submit, goToPage } = useMovieSearch();

const lastPage = computed(() => (state.value.status === 'success' ? Math.min(state.value.meta.last_page, MAX_PAGE) : 1));
</script>

<template>
    <main class="mx-auto max-w-4xl px-4 py-8">
        <h1 class="mb-6 text-3xl font-bold">{{ appName }}</h1>
        <SearchForm :disabled="state.status === 'loading'" @search="submit" />
        <StatusMessage :state="state" :query="query" />
        <template v-if="state.status === 'success'">
            <MovieList :movies="state.movies" class="mt-8" />
            <Pagination :page="page" :last-page="lastPage" class="mt-8" @change="goToPage" />
        </template>
    </main>
</template>
