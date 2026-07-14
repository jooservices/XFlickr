import { describe, expect, it } from 'vitest';

import { estimateSpiderImpact } from './spiderImpact';

describe('estimateSpiderImpact', () => {
    it('clamps inputs and computes ceilings', () => {
        const estimate = estimateSpiderImpact(-1, 0, 0, null);

        expect(estimate.max_depth).toBe(0);
        expect(estimate.max_new_contacts_per_run).toBe(1);
        expect(estimate.max_contacts_total).toBe(1);
        expect(estimate.contacts_known).toBeNull();
        expect(estimate.crawl_targets_ceiling).toBe(1 + 1 * 2);
    });

    it('caps known contacts and target estimates', () => {
        const estimate = estimateSpiderImpact(2, 5, 10, 25);

        expect(estimate.contacts_known).toBe(25);
        expect(estimate.contacts_known_capped).toBe(10);
        expect(estimate.crawl_targets_known).toBe(1 + 10 * 2);
        expect(estimate.crawl_targets_per_tick).toBe(5 * 2);
    });
});
