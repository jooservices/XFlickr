import { afterEach, describe, expect, it } from 'vitest';

import {
    dismissFlickrTokenHealthAccounts,
    filterUndismissedInvalidAccounts,
    FLICKR_TOKEN_HEALTH_BANNER_DISMISSED_KEY,
    getDismissedFlickrTokenHealthAccounts,
} from '@/lib/flickrTokenHealthBannerDismiss';

describe('flickrTokenHealthBannerDismiss', () => {
    afterEach(() => {
        sessionStorage.clear();
    });

    it('returns an empty set when nothing is dismissed', () => {
        expect(getDismissedFlickrTokenHealthAccounts()).toEqual(new Set());
    });

    it('persists dismissed account public ids in sessionStorage', () => {
        dismissFlickrTokenHealthAccounts(['acct-a', 'acct-b']);

        expect(sessionStorage.getItem(FLICKR_TOKEN_HEALTH_BANNER_DISMISSED_KEY)).toBe(
            JSON.stringify(['acct-a', 'acct-b']),
        );
        expect(getDismissedFlickrTokenHealthAccounts()).toEqual(new Set(['acct-a', 'acct-b']));
    });

    it('merges dismissals across calls', () => {
        dismissFlickrTokenHealthAccounts(['acct-a']);
        dismissFlickrTokenHealthAccounts(['acct-b']);

        expect(getDismissedFlickrTokenHealthAccounts()).toEqual(new Set(['acct-a', 'acct-b']));
    });

    it('filters out dismissed invalid accounts', () => {
        const invalid = [
            { public_id: 'acct-a', label: 'Alpha' },
            { public_id: 'acct-b', label: 'Beta' },
        ];

        dismissFlickrTokenHealthAccounts(['acct-a']);

        expect(
            filterUndismissedInvalidAccounts(invalid, getDismissedFlickrTokenHealthAccounts()),
        ).toEqual([{ public_id: 'acct-b', label: 'Beta' }]);
    });
});
