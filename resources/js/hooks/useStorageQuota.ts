import { useCallback, useEffect, useMemo, useState } from 'react';

import { usePolledResource } from '@/hooks/usePolledResource';
import {
    resolveDefaultStorageQuotaAccountId,
    writeStoredStorageQuotaAccountId,
} from '@/lib/storageQuotaAccount';
import type { StorageQuotaSnapshot, StorageQuotaState } from '@/types';

export function useStorageQuota() {
    const { data, loading } = usePolledResource<{ data: StorageQuotaSnapshot }>('/api/v1/storage/quota', {
        intervalMs: 15_000,
    });
    const snapshot = data?.data ?? null;
    const [selectedAccountId, setSelectedAccountIdState] = useState<number | null>(null);

    const setSelectedAccountId = useCallback((accountId: number) => {
        setSelectedAccountIdState(accountId);
        writeStoredStorageQuotaAccountId(accountId);
    }, []);

    useEffect(() => {
        if (!snapshot) {
            return;
        }

        setSelectedAccountIdState((current) => {
            if (current !== null && snapshot.accounts.some((row) => row.account.id === current)) {
                return current;
            }

            return resolveDefaultStorageQuotaAccountId(snapshot.accounts);
        });
    }, [snapshot]);

    const selectedRow = useMemo(() => {
        if (!snapshot || selectedAccountId === null) {
            return null;
        }

        return snapshot.accounts.find((row) => row.account.id === selectedAccountId) ?? null;
    }, [snapshot, selectedAccountId]);

    const selectedQuota = useMemo((): StorageQuotaState | null => {
        return selectedRow?.quota ?? null;
    }, [selectedRow]);

    return {
        snapshot,
        selectedAccountId,
        setSelectedAccountId,
        selectedRow,
        selectedQuota,
        loading,
    };
}

export function storageAccountLabel(account: {
    label: string;
    provider: string;
    id: number;
}): string {
    return account.label || account.provider || `Account #${account.id}`;
}
