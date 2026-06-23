import type { StorageAccount } from '@/types';

export interface StorageReauthorizationPayload {
    needs_reauthorization?: boolean;
    reauthorize_url?: string;
    missing_scopes?: StorageAccount['missing_scopes'];
}

export function applyReauthorizationToAccounts(
    accounts: StorageAccount[],
    accountId: number,
    payload: StorageReauthorizationPayload,
): StorageAccount[] {
    if (!payload.needs_reauthorization || !payload.reauthorize_url) {
        return accounts;
    }

    return accounts.map((entry) =>
        entry.id === accountId
            ? {
                  ...entry,
                  needs_reauthorization: true,
                  missing_scopes: payload.missing_scopes ?? entry.missing_scopes,
                  reauthorize_url: payload.reauthorize_url ?? entry.reauthorize_url,
              }
            : entry,
    );
}

export function isStorageReauthorizationResponse(
    status: number,
    payload: StorageReauthorizationPayload,
): payload is StorageReauthorizationPayload & { needs_reauthorization: true; reauthorize_url: string } {
    return status === 403 && payload.needs_reauthorization === true && typeof payload.reauthorize_url === 'string';
}
