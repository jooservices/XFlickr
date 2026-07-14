import { describe, expect, it } from 'vitest';

import {
    ALL_CRAWL_OPTION,
    CONTACT_CATALOG_COLUMNS,
    CONTACT_CATALOG_TYPES,
    CRAWL_TYPE_OPTIONS,
} from './contactCatalog';

describe('contactCatalog', () => {
    it('exposes catalog type metadata', () => {
        expect(CONTACT_CATALOG_TYPES).toContain('photos');
        expect(CONTACT_CATALOG_COLUMNS).toHaveLength(CONTACT_CATALOG_TYPES.length);
        expect(CRAWL_TYPE_OPTIONS[0]?.value).toBe('photos');
        expect(ALL_CRAWL_OPTION.label).toBe('All');
    });
});
