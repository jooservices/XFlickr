export const FLICKR_TOKEN_HEALTH_BANNER_DISMISSED_KEY = 'xflickr.flickr.token-health-banner.dismissed';

export function getDismissedFlickrTokenHealthAccounts(): Set<string> {
    try {
        const raw = sessionStorage.getItem(FLICKR_TOKEN_HEALTH_BANNER_DISMISSED_KEY);

        if (!raw) {
            return new Set();
        }

        const parsed = JSON.parse(raw) as unknown;

        if (!Array.isArray(parsed)) {
            return new Set();
        }

        return new Set(parsed.filter((value): value is string => typeof value === 'string'));
    } catch {
        return new Set();
    }
}

export function dismissFlickrTokenHealthAccounts(publicIds: string[]): void {
    try {
        const dismissed = getDismissedFlickrTokenHealthAccounts();

        for (const publicId of publicIds) {
            dismissed.add(publicId);
        }

        sessionStorage.setItem(
            FLICKR_TOKEN_HEALTH_BANNER_DISMISSED_KEY,
            JSON.stringify([...dismissed]),
        );
    } catch {
        // ignore quota / private mode
    }
}

export function filterUndismissedInvalidAccounts<T extends { public_id: string }>(
    invalidAccounts: T[],
    dismissed: Set<string>,
): T[] {
    return invalidAccounts.filter((account) => !dismissed.has(account.public_id));
}
