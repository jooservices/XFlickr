import type { StorageAccount } from '@/types';

export interface StorageReauthorizationPayload {
    needs_reauthorization?: boolean;
    reauthorize_url?: string;
    missing_scopes?: StorageAccount['missing_scopes'];
}

function reauthorizationFields(payload: unknown): StorageReauthorizationPayload {
    if (!payload || typeof payload !== 'object') {
        return {};
    }

    const body = payload as Record<string, unknown>;
    if (body.data && typeof body.data === 'object') {
        return body.data as StorageReauthorizationPayload;
    }

    return body as StorageReauthorizationPayload;
}

export function applyReauthorizationToAccounts(
    accounts: StorageAccount[],
    accountId: number,
    payload: unknown,
): StorageAccount[] {
    const fields = reauthorizationFields(payload);

    if (!fields.needs_reauthorization || !fields.reauthorize_url) {
        return accounts;
    }

    return accounts.map((entry) =>
        entry.id === accountId
            ? {
                  ...entry,
                  needs_reauthorization: true,
                  missing_scopes: fields.missing_scopes ?? entry.missing_scopes,
                  reauthorize_url: fields.reauthorize_url ?? entry.reauthorize_url,
              }
            : entry,
    );
}

export function isStorageReauthorizationResponse(status: number, payload: unknown): boolean {
    const fields = reauthorizationFields(payload);

    return (
        status === 403 &&
        fields.needs_reauthorization === true &&
        typeof fields.reauthorize_url === 'string'
    );
}
