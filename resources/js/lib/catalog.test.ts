import { describe, expect, it } from 'vitest';

import {
    catalogOwnerUrl,
    flickrGalleryPageUrl,
    flickrPeopleUrl,
    flickrPhotoPageUrl,
    flickrPhotosetPageUrl,
    readOwnerNsidFromUrl,
} from './catalog';

describe('catalog helpers', () => {
    it('builds owner filter URLs', () => {
        expect(catalogOwnerUrl('/photos', '1@N01')).toBe('/photos?owner_nsid=1%40N01');
        expect(catalogOwnerUrl('/favorites', '1@N01')).toBe('/favorites?subject_nsid=1%40N01');
    });

    it('builds flickr page URLs', () => {
        expect(flickrPeopleUrl('1@N01')).toContain('people/1%40N01');
        expect(flickrPhotoPageUrl('1@N01', '99')).toContain('/photos/1%40N01/99');
        expect(flickrPhotosetPageUrl('1@N01', 's1')).toContain('/sets/s1');
        expect(flickrGalleryPageUrl('1@N01', 'g1')).toContain('/galleries/g1');
    });

    it('reads owner nsid from location search', () => {
        window.history.pushState({}, '', '/photos?owner_nsid=friend%40N01');
        expect(readOwnerNsidFromUrl()).toBe('friend@N01');
    });
});
