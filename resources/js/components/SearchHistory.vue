<script setup lang="ts">
defineProps<{ items: string[] }>();

const emit = defineEmits<{ select: [text: string]; clear: [] }>();
</script>

<template>
    <section v-if="items.length > 0" aria-label="Recent searches" class="mt-4 flex flex-wrap items-center gap-2">
        <h2 class="text-sm text-gray-600">Recent searches:</h2>
        <ul class="flex flex-wrap gap-2">
            <li v-for="text in items" :key="text">
                <button
                    type="button"
                    class="max-w-48 truncate rounded-full border border-gray-300 px-3 py-1 text-sm hover:bg-gray-100"
                    @click="emit('select', text)"
                >
                    {{ text }}
                </button>
            </li>
        </ul>
        <button type="button" class="text-sm text-gray-600 underline" @click="emit('clear')">Clear</button>
    </section>
</template>
