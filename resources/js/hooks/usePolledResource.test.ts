import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { usePolledResource } from '@/hooks/usePolledResource';
import { apiGet } from '@/lib/apiClient';

vi.mock('@/lib/apiClient', () => ({
    apiGet: vi.fn(),
}));

describe('usePolledResource', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
        vi.mocked(apiGet).mockReset();
    });

    it('loads data from the URL and exposes refresh', async () => {
        vi.mocked(apiGet).mockResolvedValue({ ok: true });

        const { result } = renderHook(() => usePolledResource<{ ok: boolean }>('/api/v1/operations/snapshot'));

        await waitFor(() => {
            expect(result.current.data).toEqual({ ok: true });
        });

        expect(result.current.error).toBeNull();
        expect(apiGet).toHaveBeenCalledWith(
            '/api/v1/operations/snapshot',
            expect.objectContaining({
                signal: expect.any(AbortSignal),
            }),
        );

        vi.mocked(apiGet).mockResolvedValue({ ok: false });

        act(() => {
            result.current.refresh();
        });

        await waitFor(() => {
            expect(result.current.data).toEqual({ ok: false });
        });
    });

    it('skips polling when disabled or URL is null', async () => {
        vi.mocked(apiGet).mockResolvedValue({ ok: true });

        const { rerender } = renderHook(
            ({ url, enabled }: { url: string | null; enabled: boolean }) => usePolledResource(url, { enabled }),
            { initialProps: { url: null as string | null, enabled: true } },
        );

        await act(async () => {
            await Promise.resolve();
        });

        expect(apiGet).not.toHaveBeenCalled();

        rerender({ url: '/api/v1/dashboard/snapshot', enabled: false });

        await act(async () => {
            await Promise.resolve();
        });

        expect(apiGet).not.toHaveBeenCalled();
    });

    it('records errors from failed polls', async () => {
        vi.mocked(apiGet).mockRejectedValue(new Error('network down'));

        const { result } = renderHook(() => usePolledResource('/api/v1/operations/snapshot'));

        await waitFor(() => {
            expect(result.current.error?.message).toBe('network down');
        });

        expect(result.current.data).toBeNull();
    });
});
