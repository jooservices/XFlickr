import { describe, expect, it } from 'vitest';

import * as tableSort from './tableSort';

describe('sortClientData', () => {
    const rows = [
        { name: 'beta', count: 2 },
        { name: 'alpha', count: 10 },
        { name: 'gamma', count: null as number | null },
    ];

    it('sorts strings ascending and descending', () => {
        const asc = tableSort.sortClientData(rows, 'name', 'asc', (row, key) => row[key as 'name']);
        expect(asc.map((row) => row.name)).toEqual(['alpha', 'beta', 'gamma']);

        const desc = tableSort.sortClientData(rows, 'name', 'desc', (row, key) => row[key as 'name']);
        expect(desc.map((row) => row.name)).toEqual(['gamma', 'beta', 'alpha']);
    });

    it('sorts numbers and pushes nulls last', () => {
        const asc = tableSort.sortClientData(rows, 'count', 'asc', (row, key) => row[key as 'count']);
        expect(asc.map((row) => row.count)).toEqual([2, 10, null]);
    });
});

describe('defaultRowValue', () => {
    it('returns string and number values', () => {
        expect(tableSort.defaultRowValue({ id: 3 }, 'id')).toBe(3);
        expect(tableSort.defaultRowValue({ name: 'x' }, 'name')).toBe('x');
    });

    it('coerces booleans and ignores other types', () => {
        expect(tableSort.defaultRowValue({ ok: true }, 'ok')).toBe(1);
        expect(tableSort.defaultRowValue({ ok: false }, 'ok')).toBe(0);
        expect(tableSort.defaultRowValue({ nested: { a: 1 } }, 'nested')).toBeNull();
    });
});
