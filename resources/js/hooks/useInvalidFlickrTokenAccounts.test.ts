import { renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { useInvalidFlickrTokenAccounts } from '@/hooks/useInvalidFlickrTokenAccounts';
import { apiGet } from '@/lib/apiClient';
import type { FlickrAccount } from '@/types';

vi.mock('@/lib/apiClient', () => ({
    apiGet: vi.fn(),
}));

const connectedAccount = (overrides: Partial<FlickrAccount> = {}): FlickrAccount => ({
    public_id: 'pub-1',
    nsid: '111@N01',
    username: 'alpha',
    fullname: null,
    app_profile: 'main',
    connected_at: '2026-01-01T00:00:00Z',
    is_active: true,
    disconnected_at: null,
    ...overrides,
});

describe('useInvalidFlickrTokenAccounts', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        vi.mocked(apiGet).mockReset();
    });

    it('returns invalid connected accounts after probing token health', async () => {
        vi.mocked(apiGet).mockImplementation(async (path: string) => {
            if (path.endsWith('/pub-1/token-health')) {
                return { data: { token_valid: false } };
            }

            return { data: { token_valid: true } };
        });

        const { result } = renderHook(() =>
            useInvalidFlickrTokenAccounts([
                connectedAccount({ public_id: 'pub-1', username: 'alpha' }),
                connectedAccount({
                    public_id: 'pub-2',
                    nsid: '222@N02',
                    username: 'beta',
                }),
            ]),
        );

        await waitFor(() => {
            expect(result.current.probing).toBe(false);
        });

        expect(result.current.invalidAccounts).toEqual([
            { public_id: 'pub-1', label: 'alpha' },
        ]);
        expect(apiGet).toHaveBeenCalledTimes(2);
    });

    it('skips disconnected accounts', async () => {
        vi.mocked(apiGet).mockResolvedValue({ data: { token_valid: false } });

        const { result } = renderHook(() =>
            useInvalidFlickrTokenAccounts([
                connectedAccount({
                    public_id: 'pub-1',
                    disconnected_at: '2026-02-01T00:00:00Z',
                }),
            ]),
        );

        await waitFor(() => {
            expect(result.current.probing).toBe(false);
        });

        expect(result.current.invalidAccounts).toEqual([]);
        expect(apiGet).not.toHaveBeenCalled();
    });
});
