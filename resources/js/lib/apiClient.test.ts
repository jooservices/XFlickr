import { afterEach, describe, expect, it, vi } from 'vitest';

import { apiDelete, apiGet } from '@/lib/apiClient';
import { API_V1, apiV1Path, flickrApiV1AccountPath } from '@/lib/apiPaths';

describe('apiPaths', () => {
    it('builds v1 paths with a leading slash', () => {
        expect(API_V1).toBe('/api/v1');
        expect(apiV1Path('operations/snapshot')).toBe('/api/v1/operations/snapshot');
        expect(apiV1Path('/storage/accounts')).toBe('/api/v1/storage/accounts');
    });

    it('builds flickr account-scoped v1 paths', () => {
        expect(flickrApiV1AccountPath('abc')).toBe('/api/v1/flickr/accounts/abc');
        expect(flickrApiV1AccountPath('abc', '/contacts')).toBe('/api/v1/flickr/accounts/abc/contacts');
        expect(flickrApiV1AccountPath('abc', 'contacts')).toBe('/api/v1/flickr/accounts/abc/contacts');
    });
});

describe('apiClient', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('apiGet returns JSON on success', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                status: 200,
                text: async () => JSON.stringify({ data: { ok: true } }),
            }),
        );

        await expect(apiGet<{ data: { ok: boolean } }>('/api/v1/dashboard/snapshot')).resolves.toEqual({
            data: { ok: true },
        });
    });

    it('apiDelete sends DELETE with CSRF header when cookie is present', async () => {
        Object.defineProperty(document, 'cookie', {
            configurable: true,
            get: () => 'XSRF-TOKEN=token%2Bvalue',
        });

        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            text: async () => JSON.stringify({ deleted: true }),
        });
        vi.stubGlobal('fetch', fetchMock);

        await expect(apiDelete<{ deleted: boolean }>('/api/v1/storage/onedrive/files', { ids: ['1'] })).resolves.toEqual({
            deleted: true,
        });

        expect(fetchMock).toHaveBeenCalledWith(
            '/api/v1/storage/onedrive/files',
            expect.objectContaining({
                method: 'DELETE',
                headers: expect.objectContaining({
                    'X-XSRF-TOKEN': 'token+value',
                }),
            }),
        );
    });
});
