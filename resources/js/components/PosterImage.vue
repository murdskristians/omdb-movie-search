<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{ src: string | null; alt: string }>();

const failed = ref(false);

// A new poster address deserves a fresh try, even if the previous one failed to load.
watch(
    () => props.src,
    () => {
        failed.value = false;
    },
);
</script>

<template>
    <img
        v-if="src && !failed"
        :src="src"
        :alt="alt"
        loading="lazy"
        class="aspect-[2/3] w-full rounded object-cover"
        @error="failed = true"
    />
    <div
        v-else
        role="img"
        :aria-label="`No poster for ${alt}`"
        class="flex aspect-[2/3] w-full items-center justify-center rounded bg-gray-200 text-sm text-gray-500"
        data-testid="poster-placeholder"
    >
        No poster
    </div>
</template>
