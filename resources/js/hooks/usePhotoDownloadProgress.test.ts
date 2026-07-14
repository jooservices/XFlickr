import { describe, expect, it } from 'vitest';

import { isLiveDownloadStatus } from '@/hooks/usePhotoDownloadProgress';

describe('usePhotoDownloadProgress helpers', () => {
    it('treats pending and downloading as live', () => {
        expect(isLiveDownloadStatus('pending')).toBe(true);
        expect(isLiveDownloadStatus('downloading')).toBe(true);
        expect(isLiveDownloadStatus('completed')).toBe(false);
        expect(isLiveDownloadStatus('none')).toBe(false);
        expect(isLiveDownloadStatus(undefined)).toBe(false);
    });
});
