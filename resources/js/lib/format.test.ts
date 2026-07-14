import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { formatBytes, formatCount, formatRelativeTime, formatSyncedAt } from './format';

describe('formatCount', () => {
    it('formats numbers and nullish values', () => {
        expect(formatCount(null)).toBe('—');
        expect(formatCount(undefined)).toBe('—');
        expect(formatCount(0)).toBe('0');
        expect(formatCount(1204)).toBe('1,204');
    });
});

describe('formatBytes', () => {
    it('formats across size units', () => {
        expect(formatBytes(null)).toBe('—');
        expect(formatBytes(512)).toBe('512 B');
        expect(formatBytes(2048)).toBe('2.0 KB');
        expect(formatBytes(2 * 1024 * 1024)).toBe('2.0 MB');
        expect(formatBytes(3 * 1024 * 1024 * 1024)).toBe('3.0 GB');
        expect(formatBytes(4 * 1024 * 1024 * 1024 * 1024)).toBe('4.0 TB');
    });
});

describe('formatSyncedAt', () => {
    it('returns null for empty values', () => {
        expect(formatSyncedAt(null)).toBeNull();
        expect(formatSyncedAt(undefined)).toBeNull();
        expect(formatSyncedAt('')).toBeNull();
    });

    it('formats valid dates', () => {
        expect(formatSyncedAt('2026-01-01T00:00:00.000Z')).toEqual(expect.any(String));
    });
});

describe('formatRelativeTime', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-01-01T12:00:00.000Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('formats empty and invalid inputs', () => {
        expect(formatRelativeTime(null)).toBe('—');
        expect(formatRelativeTime('not-a-date')).toBe('not-a-date');
    });

    it('formats recent relative windows', () => {
        expect(formatRelativeTime('2026-01-01T11:59:30.000Z')).toBe('30s ago');
        expect(formatRelativeTime('2026-01-01T11:30:00.000Z')).toBe('30m ago');
        expect(formatRelativeTime('2026-01-01T10:00:00.000Z')).toBe('2h ago');
    });
});
