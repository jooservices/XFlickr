import type { StorageQuotaAccountSummary } from '@/types';

export const STORAGE_QUOTA_ACCOUNT_STORAGE_KEY = 'xflickr:status-footer-storage-quota-account';

export function readStoredStorageQuotaAccountId(): number | null {
    try {
        const raw = localStorage.getItem(STORAGE_QUOTA_ACCOUNT_STORAGE_KEY);
        if (!raw) {
            return null;
        }

        const parsed = Number.parseInt(raw, 10);
        return Number.isFinite(parsed) ? parsed : null;
    } catch {
        return null;
    }
}

export function writeStoredStorageQuotaAccountId(accountId: number): void {
    try {
        localStorage.setItem(STORAGE_QUOTA_ACCOUNT_STORAGE_KEY, String(accountId));
    } catch {
        // ignore quota / private mode
    }
}

export function resolveDefaultStorageQuotaAccountId(
    accounts: StorageQuotaAccountSummary[],
): number | null {
    const stored = readStoredStorageQuotaAccountId();
    if (stored !== null && accounts.some((row) => row.account.id === stored)) {
        return stored;
    }

    const preferred =
        accounts.find((row) => row.status === 'ok') ??
        accounts.find((row) => row.account.is_default) ??
        accounts[0];

    return preferred?.account.id ?? null;
}
