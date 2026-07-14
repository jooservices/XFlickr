import { describe, expect, it } from 'vitest';

import { apiV1Path, flickrApiV1AccountPath } from './apiPaths';
import { cn } from './cn';
import { connectionsPath } from './connections';

describe('apiPaths', () => {
    it('normalizes api v1 suffixes', () => {
        expect(apiV1Path('dashboard')).toBe('/api/v1/dashboard');
        expect(apiV1Path('/dashboard')).toBe('/api/v1/dashboard');
        expect(flickrApiV1AccountPath('abc')).toBe('/api/v1/flickr/accounts/abc');
        expect(flickrApiV1AccountPath('abc', 'token-health')).toBe('/api/v1/flickr/accounts/abc/token-health');
        expect(flickrApiV1AccountPath('abc', '/contacts')).toBe('/api/v1/flickr/accounts/abc/contacts');
    });
});

describe('connectionsPath', () => {
    it('builds flickr and storage connection paths', () => {
        expect(connectionsPath()).toBe('/connections');
        expect(connectionsPath({ provider: 'flickr' })).toBe('/connections');
        expect(connectionsPath({ provider: 'storage' })).toBe('/connections?provider=storage');
    });
});

describe('cn', () => {
    it('merges class names', () => {
        expect(cn('px-2', 'px-4')).toBe('px-4');
    });
});
