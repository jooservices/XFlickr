import { describe, expect, it } from 'vitest';

import {
    filterCommandPaletteItems,
    isCommandPaletteToggleEvent,
} from '@/lib/commandPaletteFilter';

describe('commandPaletteFilter', () => {
    const items = [
        { id: 'a', label: 'Contacts', keywords: ['people', 'nsid'] },
        { id: 'b', label: 'Operations', keywords: ['crawl', 'jobs'] },
        { id: 'c', label: 'Settings', keywords: ['runtime'] },
    ];

    it('returns all items for an empty query', () => {
        expect(filterCommandPaletteItems(items, '   ')).toEqual(items);
    });

    it('filters by label substring', () => {
        expect(filterCommandPaletteItems(items, 'set').map((item) => item.id)).toEqual(['c']);
    });

    it('filters by keyword substring', () => {
        expect(filterCommandPaletteItems(items, 'crawl').map((item) => item.id)).toEqual(['b']);
    });

    it('is case-insensitive', () => {
        expect(filterCommandPaletteItems(items, 'CONTACT').map((item) => item.id)).toEqual(['a']);
    });

    it('detects Cmd/Ctrl+K toggle keys', () => {
        expect(
            isCommandPaletteToggleEvent(
                new KeyboardEvent('keydown', { key: 'k', metaKey: true }),
            ),
        ).toBe(true);
        expect(
            isCommandPaletteToggleEvent(
                new KeyboardEvent('keydown', { key: 'K', ctrlKey: true }),
            ),
        ).toBe(true);
        expect(
            isCommandPaletteToggleEvent(new KeyboardEvent('keydown', { key: 'k' })),
        ).toBe(false);
    });
});
