<script setup lang="ts">
defineProps<{ page: number; lastPage: number }>();

const emit = defineEmits<{ change: [page: number] }>();
</script>

<template>
    <nav v-if="lastPage > 1" aria-label="Pagination" class="flex flex-wrap items-center justify-center gap-4">
        <button
            type="button"
            :disabled="page <= 1"
            class="min-h-11 rounded border border-gray-300 px-4 disabled:opacity-50"
            @click="emit('change', page - 1)"
        >
            Previous
        </button>
        <span aria-live="polite">Page {{ page }} of {{ lastPage }}</span>
        <button
            type="button"
            :disabled="page >= lastPage"
            class="min-h-11 rounded border border-gray-300 px-4 disabled:opacity-50"
            @click="emit('change', page + 1)"
        >
            Next
        </button>
    </nav>
</template>
