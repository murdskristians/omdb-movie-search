<script setup lang="ts">
import { computed, onMounted } from 'vue';
import MovieDetailsModal from './components/MovieDetailsModal.vue';
import MovieList from './components/MovieList.vue';
import Pagination from './components/Pagination.vue';
import SearchForm from './components/SearchForm.vue';
import SearchHistory from './components/SearchHistory.vue';
import StatusMessage from './components/StatusMessage.vue';
import { useMovieDetails } from './composables/useMovieDetails';
import { useMovieSearch } from './composables/useMovieSearch';
import { useSearchHistory } from './composables/useSearchHistory';

const appName = 'OMDb Movie Search';

// The backend (and OMDb) accept pages 1 to 100 only, even if a search matches more movies.
const MAX_PAGE = 100;

const { state, query, page, submit, goToPage } = useMovieSearch();
const { items: recentSearches, refresh: refreshHistory, clear: clearHistory } = useSearchHistory();

const { state: details, open: openDetails, close: closeDetails } = useMovieDetails();

const lastPage = computed(() => (state.value.status === 'success' ? Math.min(state.value.meta.last_page, MAX_PAGE) : 1));

onMounted(refreshHistory);

// The server records a search while answering it, so ask for the list again once it is done.
async function search(text: string): Promise<void> {
    await submit(text);
    await refreshHistory();
}
</script>

<template>
    <main class="mx-auto max-w-4xl px-4 py-8">
        <h1 class="mb-6 text-3xl font-bold">{{ appName }}</h1>
        <SearchForm :disabled="state.status === 'loading'" @search="search" />
        <SearchHistory :items="recentSearches" @select="search" @clear="clearHistory" />
        <StatusMessage :state="state" :query="query" />
        <template v-if="state.status === 'success'">
            <MovieList :movies="state.movies" class="mt-8" @select="openDetails" />
            <Pagination :page="page" :last-page="lastPage" class="mt-8" @change="goToPage" />
        </template>
        <MovieDetailsModal :state="details" @close="closeDetails" />
    </main>
</template>
