import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useFlashToast } from './useFlashToast';
import { useOwnerNsidFilter } from './useOwnerNsidFilter';

vi.mock('@/lib/toast', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}));

vi.mock('@/lib/catalog', () => ({
    readOwnerNsidFromUrl: () => '',
}));

describe('useOwnerNsidFilter', () => {
    it('applies and clears draft filters', () => {
        const { result } = renderHook(() => useOwnerNsidFilter('owner_nsid'));

        act(() => {
            result.current.setDraft('friend@N01');
        });
        act(() => {
            result.current.apply();
        });

        expect(result.current.filters).toEqual({ owner_nsid: 'friend@N01' });
        expect(result.current.hasActiveFilter).toBe(true);

        act(() => {
            result.current.clear();
        });
        expect(result.current.hasActiveFilter).toBe(false);
    });
});

describe('useFlashToast', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('emits success and error toasts once per message', async () => {
        const { toast } = await import('@/lib/toast');
        const { rerender } = renderHook(
            ({ flash }) => useFlashToast(flash),
            {
                initialProps: {
                    flash: { success: 'Saved', error: null as string | null },
                },
            },
        );

        expect(toast.success).toHaveBeenCalledWith('Saved');

        rerender({ flash: { success: 'Saved', error: 'Boom' } });
        expect(toast.error).toHaveBeenCalledWith('Boom');
    });
});
