export type OnboardingFlags = {
    hasFlickrAccounts: boolean;
    hasStorageAccounts: boolean;
    hasCompletedCrawl: boolean;
};

export type OnboardingStepId = 'flickr' | 'crawl' | 'storage';

export type OnboardingStep = {
    id: OnboardingStepId;
    label: string;
    done: boolean;
    optional?: boolean;
};

/**
 * Connect Flickr → first crawl → optional storage.
 * Auto-hide when Flickr is connected and a first crawl has produced catalog data.
 */
export function shouldShowOnboarding(
    flags: OnboardingFlags,
    dismissed: boolean,
): boolean {
    if (dismissed) {
        return false;
    }

    return !(flags.hasFlickrAccounts && flags.hasCompletedCrawl);
}

export function buildOnboardingSteps(flags: OnboardingFlags): OnboardingStep[] {
    return [
        {
            id: 'flickr',
            label: flags.hasFlickrAccounts
                ? 'Flickr connected.'
                : 'Add Flickr API credentials and connect an account.',
            done: flags.hasFlickrAccounts,
        },
        {
            id: 'crawl',
            label: flags.hasCompletedCrawl
                ? 'First crawl complete — catalog is filling in.'
                : 'From Contacts or Catalog, trigger a manual crawl for the contacts you want to archive.',
            done: flags.hasCompletedCrawl,
        },
        {
            id: 'storage',
            label: flags.hasStorageAccounts
                ? 'Storage connected — queue uploads from Contacts or Catalog.'
                : 'Optional: connect Google Photos, Drive, OneDrive, or R2 under Connections → Storage.',
            done: flags.hasStorageAccounts,
            optional: true,
        },
    ];
}

export function dashboardHasCompletedCrawl(
    accounts: Array<{
        contacts_db?: number;
        photos_db?: number;
        runs?: { completed?: number };
    }>,
): boolean {
    return accounts.some(
        (row) =>
            (row.contacts_db ?? 0) > 0 ||
            (row.photos_db ?? 0) > 0 ||
            (row.runs?.completed ?? 0) > 0,
    );
}
