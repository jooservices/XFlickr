import type { FlickrAccount } from '@/types';

export const FLICKR_QUOTA_ACCOUNT_STORAGE_KEY = 'xflickr:navbar-quota-account';

export function readStoredFlickrQuotaNsid(): string | null {
    try {
        return localStorage.getItem(FLICKR_QUOTA_ACCOUNT_STORAGE_KEY);
    } catch {
        return null;
    }
}

export function writeStoredFlickrQuotaNsid(nsid: string): void {
    try {
        localStorage.setItem(FLICKR_QUOTA_ACCOUNT_STORAGE_KEY, nsid);
    } catch {
        // ignore quota / private mode
    }
}

export function resolveDefaultFlickrQuotaNsid(
    accounts: FlickrAccount[],
    activeConnectionKey?: string | null,
): string | null {
    const stored = readStoredFlickrQuotaNsid();
    if (stored && accounts.some((account) => account.nsid === stored)) {
        return stored;
    }

    if (activeConnectionKey && accounts.some((account) => account.nsid === activeConnectionKey)) {
        return activeConnectionKey;
    }

    return accounts[0]?.nsid ?? null;
}
