import { describe, expect, it } from 'vitest';

import { adjacentPhotoIndex, findPhotoIndex } from '@/lib/photoNavigation';
import type { Photo } from '@/types';

function photo(id: number): Photo {
    return {
        id,
        flickr_photo_id: `flickr-${id}`,
        owner_nsid: 'owner',
        title: null,
        secret: null,
        server: null,
        farm: null,
    };
}

describe('photoNavigation', () => {
    const photos = [photo(1), photo(2), photo(3)];

    it('finds the current photo index', () => {
        expect(findPhotoIndex(photos, photo(2))).toBe(1);
        expect(findPhotoIndex(photos, photo(99))).toBe(-1);
    });

    it('returns adjacent indexes within bounds', () => {
        expect(adjacentPhotoIndex(photos, photo(2), -1)).toBe(0);
        expect(adjacentPhotoIndex(photos, photo(2), 1)).toBe(2);
    });

    it('returns null at list ends', () => {
        expect(adjacentPhotoIndex(photos, photo(1), -1)).toBeNull();
        expect(adjacentPhotoIndex(photos, photo(3), 1)).toBeNull();
    });

    it('returns null when the current photo is not in the list', () => {
        expect(adjacentPhotoIndex(photos, photo(99), 1)).toBeNull();
    });
});
