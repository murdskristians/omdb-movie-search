import { ref } from 'vue';
import { clearHistory, getHistory } from '../api/client';

export function useSearchHistory(load: typeof getHistory = getHistory, remove: typeof clearHistory = clearHistory) {
    const items = ref<string[]>([]);
    // Set when the visitor asked to clear the list and that did not work.
    const error = ref<string | null>(null);

    // The history is a convenience. If it cannot be loaded, the search must keep working,
    // so a failure leaves the list as it was instead of raising an error on screen.
    async function refresh(): Promise<void> {
        try {
            items.value = await load();
            error.value = null;
        } catch {
            // keep the current list
        }
    }

    // Clearing is something the visitor asked for, so a failure is reported and the list stays as it was.
    async function clear(): Promise<void> {
        try {
            await remove();
            items.value = [];
            error.value = null;
        } catch {
            error.value = 'Could not clear the recent searches. Please try again.';
        }
    }

    return { items, error, refresh, clear };
}
