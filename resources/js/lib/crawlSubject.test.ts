import { describe, expect, it } from 'vitest';

import { crawlSubjectForAccount, crawlSubjectForContact, crawlSubjectForPhoto } from './crawlSubject';

describe('crawlSubject helpers', () => {
    it('prefers username then fullname for accounts', () => {
        expect(crawlSubjectForAccount({ username: 'alice', fullname: 'Alice', nsid: '1@N01' })).toEqual({
            title: 'alice',
            nsid: '1@N01',
        });
        expect(crawlSubjectForAccount({ username: null, fullname: 'Alice', nsid: '1@N01' }).title).toBe('Alice');
        expect(crawlSubjectForAccount({ username: null, fullname: null, nsid: '1@N01' }).title).toBe('1@N01');
    });

    it('prefers username then realname for contacts', () => {
        expect(crawlSubjectForContact({ username: null, realname: 'Bob', nsid: '2@N01' }).title).toBe('Bob');
    });

    it('uses trimmed photo title when present', () => {
        expect(
            crawlSubjectForPhoto({ flickr_photo_id: '99', owner_nsid: '3@N01', title: '  Sunset  ' }),
        ).toEqual({ title: 'Sunset', nsid: '3@N01' });
        expect(crawlSubjectForPhoto({ flickr_photo_id: '99', title: '   ' }).title).toBe('99');
    });
});
