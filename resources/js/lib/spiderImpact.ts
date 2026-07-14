/**
 * Mirrors Modules\Spider\Services\SpiderImpactEstimator for live modal previews.
 */

export const SPIDER_CRAWL_TARGETS_PER_CONTACT = 2;
export const SPIDER_SEED_CRAWL_TARGETS = 1;

export type SpiderImpactEstimate = {
    seed_crawl_targets: number;
    crawl_targets_per_contact: number;
    contacts_known: number | null;
    contacts_known_capped: number | null;
    crawl_targets_known: number | null;
    contacts_ceiling: number;
    crawl_targets_ceiling: number;
    crawl_targets_per_tick: number;
    max_depth: number;
    max_new_contacts_per_run: number;
    max_contacts_total: number;
};

export function estimateSpiderImpact(
    maxDepth: number,
    maxNewContactsPerRun: number,
    maxContactsTotal: number,
    savedContactsCount: number | null = null,
): SpiderImpactEstimate {
    const depth = Math.max(0, Math.trunc(maxDepth));
    const batch = Math.max(1, Math.trunc(maxNewContactsPerRun));
    const total = Math.max(1, Math.trunc(maxContactsTotal));
    const known = savedContactsCount === null ? null : Math.max(0, Math.trunc(savedContactsCount));
    const knownCapped = known === null ? null : Math.min(known, total);
    const knownTargets =
        knownCapped === null
            ? null
            : SPIDER_SEED_CRAWL_TARGETS + knownCapped * SPIDER_CRAWL_TARGETS_PER_CONTACT;

    return {
        seed_crawl_targets: SPIDER_SEED_CRAWL_TARGETS,
        crawl_targets_per_contact: SPIDER_CRAWL_TARGETS_PER_CONTACT,
        contacts_known: known,
        contacts_known_capped: knownCapped,
        crawl_targets_known: knownTargets,
        contacts_ceiling: total,
        crawl_targets_ceiling: SPIDER_SEED_CRAWL_TARGETS + total * SPIDER_CRAWL_TARGETS_PER_CONTACT,
        crawl_targets_per_tick: batch * SPIDER_CRAWL_TARGETS_PER_CONTACT,
        max_depth: depth,
        max_new_contacts_per_run: batch,
        max_contacts_total: total,
    };
}
