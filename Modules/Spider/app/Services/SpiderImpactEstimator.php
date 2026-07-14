<?php

declare(strict_types=1);

namespace Modules\Spider\Services;

/**
 * Rough crawl-target estimates for spider runs (not exact job counts).
 *
 * Per frontier contact the planner queues Photos + Contacts (= 2 crawl targets).
 * Starting a run also queues one owner Contacts crawl (seed).
 */
final class SpiderImpactEstimator
{
    public const CRAWL_TARGETS_PER_CONTACT = 2;

    public const SEED_CRAWL_TARGETS = 1;

    /**
     * @return array{
     *     seed_crawl_targets: int,
     *     crawl_targets_per_contact: int,
     *     contacts_known: int|null,
     *     contacts_known_capped: int|null,
     *     crawl_targets_known: int|null,
     *     contacts_ceiling: int,
     *     crawl_targets_ceiling: int,
     *     crawl_targets_per_tick: int,
     *     max_depth: int,
     *     max_new_contacts_per_run: int,
     *     max_contacts_total: int
     * }
     */
    public function estimate(
        int $maxDepth,
        int $maxNewContactsPerRun,
        int $maxContactsTotal,
        ?int $savedContactsCount = null,
    ): array {
        $maxDepth = max(0, $maxDepth);
        $maxNewContactsPerRun = max(1, $maxNewContactsPerRun);
        $maxContactsTotal = max(1, $maxContactsTotal);

        $contactsKnown = $savedContactsCount === null ? null : max(0, $savedContactsCount);
        $contactsKnownCapped = $contactsKnown === null
            ? null
            : min($contactsKnown, $maxContactsTotal);

        $crawlTargetsKnown = $contactsKnownCapped === null
            ? null
            : self::SEED_CRAWL_TARGETS + ($contactsKnownCapped * self::CRAWL_TARGETS_PER_CONTACT);

        return [
            'seed_crawl_targets' => self::SEED_CRAWL_TARGETS,
            'crawl_targets_per_contact' => self::CRAWL_TARGETS_PER_CONTACT,
            'contacts_known' => $contactsKnown,
            'contacts_known_capped' => $contactsKnownCapped,
            'crawl_targets_known' => $crawlTargetsKnown,
            'contacts_ceiling' => $maxContactsTotal,
            'crawl_targets_ceiling' => self::SEED_CRAWL_TARGETS + ($maxContactsTotal * self::CRAWL_TARGETS_PER_CONTACT),
            'crawl_targets_per_tick' => $maxNewContactsPerRun * self::CRAWL_TARGETS_PER_CONTACT,
            'max_depth' => $maxDepth,
            'max_new_contacts_per_run' => $maxNewContactsPerRun,
            'max_contacts_total' => $maxContactsTotal,
        ];
    }
}
