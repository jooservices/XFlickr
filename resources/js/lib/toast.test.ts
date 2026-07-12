import { describe, expect, it } from 'vitest';

import { resetToastsForTests, subscribeToasts, toast } from '@/lib/toast';

describe('toast', () => {
    it('pushes and dismisses success toasts', () => {
        resetToastsForTests();

        const seen: string[] = [];
        const unsubscribe = subscribeToasts((items) => {
            seen.push(items.map((item) => item.message).join('|'));
        });

        toast.success('Crawl started.');
        expect(seen.at(-1)).toBe('Crawl started.');

        const id = toast.error('Something failed.');
        expect(seen.at(-1)).toContain('Something failed.');

        toast.dismiss(id);
        expect(seen.at(-1)).toBe('Crawl started.');

        unsubscribe();
        resetToastsForTests();
    });
});
