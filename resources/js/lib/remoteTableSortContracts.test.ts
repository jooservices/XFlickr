import { describe, expect, it } from 'vitest';

import { CONTACT_CATALOG_COLUMNS } from './contactCatalog';

/**
 * Frontend DataTable column `key` values for remote (server) sorts must stay in
 * these allowlists. Keep in sync with:
 * - ContactListSorter::SORTABLE_COLUMNS
 * - CatalogQueryRepository::PHOTO_SORTS / PHOTOSET_SORTS / GALLERY_SORTS / FAVORITE_SORTS
 */
const CONTACT_LIST_SORTER = [
    'nsid',
    'username',
    'photos_count',
    'favorites_count',
    'photosets_count',
    'galleries_count',
    'downloads_count',
] as const;

const PHOTO_SORTS = ['title', 'flickr_photo_id', 'owner_nsid', 'id'] as const;
const PHOTOSET_SORTS = ['title', 'photo_count', 'owner_nsid', 'flickr_photoset_id', 'id'] as const;
const GALLERY_SORTS = ['title', 'photo_count', 'owner_nsid', 'flickr_gallery_id', 'id'] as const;
const FAVORITE_SORTS = ['subject_nsid', 'photo_owner_nsid', 'xflickr_photo_id', 'discovered_at', 'id'] as const;

/** Sortable DataTable keys used by Catalog / Contacts pages (excluding non-sortable columns). */
const CONTACTS_TABLE_SORT_KEYS = [
    'nsid',
    ...CONTACT_CATALOG_COLUMNS.map((column) => column.countKey),
    'downloads_count',
] as const;

const PHOTOS_TABLE_SORT_KEYS = ['title', 'flickr_photo_id', 'owner_nsid'] as const;
const PHOTOSETS_TABLE_SORT_KEYS = ['title', 'photo_count', 'flickr_photoset_id', 'owner_nsid'] as const;
const GALLERIES_TABLE_SORT_KEYS = ['title', 'photo_count', 'flickr_gallery_id', 'owner_nsid'] as const;
const FAVORITES_TABLE_SORT_KEYS = ['xflickr_photo_id', 'subject_nsid', 'photo_owner_nsid'] as const;

describe('remote table sort contracts', () => {
    it('Contacts catalog columns sort by *_count keys allowed by ContactListSorter', () => {
        for (const key of CONTACTS_TABLE_SORT_KEYS) {
            expect(CONTACT_LIST_SORTER).toContain(key);
        }

        expect(CONTACT_CATALOG_COLUMNS.map((column) => column.key)).toEqual([
            'photos',
            'favorites',
            'photosets',
            'galleries',
        ]);
        expect(CONTACT_CATALOG_COLUMNS.map((column) => column.countKey)).toEqual([
            'photos_count',
            'favorites_count',
            'photosets_count',
            'galleries_count',
        ]);
    });

    it('Catalog Photos sortable keys are in PHOTO_SORTS', () => {
        for (const key of PHOTOS_TABLE_SORT_KEYS) {
            expect(PHOTO_SORTS).toContain(key);
        }
    });

    it('Catalog Photosets sortable keys are in PHOTOSET_SORTS', () => {
        for (const key of PHOTOSETS_TABLE_SORT_KEYS) {
            expect(PHOTOSET_SORTS).toContain(key);
        }
    });

    it('Catalog Galleries sortable keys are in GALLERY_SORTS', () => {
        for (const key of GALLERIES_TABLE_SORT_KEYS) {
            expect(GALLERY_SORTS).toContain(key);
        }
    });

    it('Catalog Favorites sortable keys are in FAVORITE_SORTS', () => {
        for (const key of FAVORITES_TABLE_SORT_KEYS) {
            expect(FAVORITE_SORTS).toContain(key);
        }
    });
});
