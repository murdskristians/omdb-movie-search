import { ref } from 'vue';
import { clearHistory, getHistory } from '../api/client';

export function useSearchHistory(load: typeof getHistory = getHistory, remove: typeof clearHistory = clearHistory) {
    const items = ref<string[]>([]);

    // The history is a convenience. If it cannot be loaded or cleared, the search must keep working,
    // so a failure leaves the list as it was instead of raising an error on screen.
    async function refresh(): Promise<void> {
        try {
            items.value = await load();
        } catch {
            // keep the current list
        }
    }

    async function clear(): Promise<void> {
        try {
            await remove();
            items.value = [];
        } catch {
            // keep the current list
        }
    }

    return { items, refresh, clear };
}
