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
        const { items, error, refresh, clear } = useSearchHistory(vi.fn<Load>().mockResolvedValue(['batman']), remove);
        await refresh();

        await clear();

        expect(items.value).toEqual(['batman']);
        expect(error.value).toBe('Could not clear the recent searches. Please try again.');
    });

    it('has no error to show before anything went wrong', () => {
        const { error } = useSearchHistory(vi.fn<Load>(), vi.fn<Remove>());

        expect(error.value).toBeNull();
    });

    it('forgets the error when clearing works the next time', async () => {
        const remove = vi
            .fn<Remove>()
            .mockRejectedValueOnce(new ApiError('CSRF token mismatch.', 419))
            .mockResolvedValueOnce(undefined);
        const { items, error, refresh, clear } = useSearchHistory(vi.fn<Load>().mockResolvedValue(['batman']), remove);
        await refresh();
        await clear();

        await clear();

        expect(error.value).toBeNull();
        expect(items.value).toEqual([]);
    });

    it('does not report a failed refresh', async () => {
        const failing = vi.fn<Load>().mockRejectedValue(new ApiError('Cannot reach the server.', null));
        const { error, refresh } = useSearchHistory(failing, vi.fn<Remove>());

        await refresh();

        expect(error.value).toBeNull();
    });
});
