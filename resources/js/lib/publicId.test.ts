import { describe, expect, it } from 'vitest';

import { shortPublicId } from './publicId';

describe('shortPublicId', () => {
    it('truncates to the requested length', () => {
        expect(shortPublicId('abcdefghijklmnop')).toBe('abcdefgh');
        expect(shortPublicId('abcdefghijklmnop', 4)).toBe('abcd');
    });
});
