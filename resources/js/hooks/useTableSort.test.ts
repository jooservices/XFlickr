import { act, renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { formatCountdown, useCountdown } from './useCountdown';
import { useTableSelection } from './useTableSelection';
import { useTableSort } from './useTableSort';

describe('useTableSort', () => {
    it('updates sort key and direction', () => {
        const { result } = renderHook(() => useTableSort({ initialSort: 'name', initialDirection: 'asc' }));

        expect(result.current.sortKey).toBe('name');
        expect(result.current.sortDirection).toBe('asc');

        act(() => {
            result.current.handleSortChange('id', 'desc');
        });

        expect(result.current.sortKey).toBe('id');
        expect(result.current.sortDirection).toBe('desc');
    });
});

describe('useCountdown', () => {
    it('formats countdown strings', () => {
        expect(formatCountdown(0)).toBe('now');
        expect(formatCountdown(45)).toBe('45s');
        expect(formatCountdown(125)).toBe('2m 5s');
        expect(formatCountdown(3725)).toBe('1h 2m');
    });

    it('counts down from initial seconds', () => {
        const { result } = renderHook(() => useCountdown(null, 3));
        expect(result.current).toBe(3);
    });
});

describe('useTableSelection', () => {
    const rows = [
        { id: 'a' },
        { id: 'b' },
        { id: 'c' },
    ];

    it('toggles and clears selection', () => {
        const { result } = renderHook(() =>
            useTableSelection({
                rows,
                rowKey: (row) => row.id,
            }),
        );

        act(() => {
            result.current.toggle('a');
            result.current.toggle('b');
        });
        expect(result.current.selectedCount).toBe(2);
        expect(result.current.selectionState).toBe('partial');

        act(() => {
            result.current.togglePage();
        });
        expect(result.current.selectionState).toBe('all');

        act(() => {
            result.current.clear();
        });
        expect(result.current.hasSelection).toBe(false);
    });
});
