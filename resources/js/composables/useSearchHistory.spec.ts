import { describe, expect, it, vi } from 'vitest';
import { ApiError, type clearHistory, type getHistory } from '../api/client';
import { useSearchHistory } from './useSearchHistory';

type Load = typeof getHistory;
type Remove = typeof clearHistory;

describe('useSearchHistory', () => {
    it('starts with an empty list', () => {
        const { items } = useSearchHistory(vi.fn<Load>(), vi.fn<Remove>());

        expect(items.value).toEqual([]);
    });

    it('refresh loads the list from the server', async () => {
        const { items, refresh } = useSearchHistory(vi.fn<Load>().mockResolvedValue(['matrix', 'batman']));

        await refresh();

        expect(items.value).toEqual(['matrix', 'batman']);
    });

    it('refresh replaces the old list with the new one', async () => {
        const load = vi.fn<Load>().mockResolvedValueOnce(['batman']).mockResolvedValueOnce(['matrix', 'batman']);
        const { items, refresh } = useSearchHistory(load);

        await refresh();
        await refresh();

        expect(items.value).toEqual(['matrix', 'batman']);
    });

    it('keeps the current list when loading fails', async () => {
        const load = vi
            .fn<Load>()
            .mockResolvedValueOnce(['batman'])
            .mockRejectedValueOnce(new ApiError('Cannot reach the server.', null));
        const { items, refresh } = useSearchHistory(load);

        await refresh();
        await refresh();

        expect(items.value).toEqual(['batman']);
    });

    it('clear asks the server and empties the list', async () => {
        const remove = vi.fn<Remove>().mockResolvedValue(undefined);
        const { items, refresh, clear } = useSearchHistory(vi.fn<Load>().mockResolvedValue(['batman']), remove);
        await refresh();

        await clear();

        expect(remove).toHaveBeenCalledOnce();
        expect(items.value).toEqual([]);
    });

    it('keeps the list when clearing fails', async () => {
        const remove = vi.fn<Remove>().mockRejectedValue(new ApiError('CSRF token mismatch.', 419));
        const { items, refresh, clear } = useSearchHistory(vi.fn<Load>().mockResolvedValue(['batman']), remove);
        await refresh();

        await clear();

        expect(items.value).toEqual(['batman']);
    });
});
