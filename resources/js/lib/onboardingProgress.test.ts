import { describe, expect, it } from 'vitest';

import {
    buildOnboardingSteps,
    dashboardHasCompletedCrawl,
    shouldShowOnboarding,
} from '@/lib/onboardingProgress';

describe('onboardingProgress', () => {
    it('hides when dismissed', () => {
        expect(
            shouldShowOnboarding(
                { hasFlickrAccounts: false, hasStorageAccounts: false, hasCompletedCrawl: false },
                true,
            ),
        ).toBe(false);
    });

    it('hides when flickr is connected and crawl has data', () => {
        expect(
            shouldShowOnboarding(
                { hasFlickrAccounts: true, hasStorageAccounts: false, hasCompletedCrawl: true },
                false,
            ),
        ).toBe(false);
    });

    it('shows when flickr is connected but no crawl yet', () => {
        expect(
            shouldShowOnboarding(
                { hasFlickrAccounts: true, hasStorageAccounts: true, hasCompletedCrawl: false },
                false,
            ),
        ).toBe(true);
    });

    it('builds step completion flags', () => {
        const steps = buildOnboardingSteps({
            hasFlickrAccounts: true,
            hasStorageAccounts: false,
            hasCompletedCrawl: false,
        });

        expect(steps.map((step) => [step.id, step.done])).toEqual([
            ['flickr', true],
            ['crawl', false],
            ['storage', false],
        ]);
        expect(steps[2]?.optional).toBe(true);
    });

    it('detects completed crawl from dashboard rows', () => {
        expect(dashboardHasCompletedCrawl([{ contacts_db: 0, photos_db: 0, runs: { completed: 0 } }])).toBe(
            false,
        );
        expect(dashboardHasCompletedCrawl([{ contacts_db: 3, photos_db: 0 }])).toBe(true);
        expect(dashboardHasCompletedCrawl([{ photos_db: 12 }])).toBe(true);
        expect(dashboardHasCompletedCrawl([{ runs: { completed: 1 } }])).toBe(true);
    });
});
