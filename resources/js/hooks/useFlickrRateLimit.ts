import { useCallback, useEffect, useMemo, useState } from 'react';

import { usePolledResource } from '@/hooks/usePolledResource';
import {
    resolveDefaultFlickrQuotaNsid,
    writeStoredFlickrQuotaNsid,
} from '@/lib/flickrQuotaAccount';
import type { FlickrCatalogCounts, FlickrRateLimitSnapshot, RateLimitState } from '@/types';

export function useFlickrRateLimit() {
    const { data, loading } = usePolledResource<{ data: FlickrRateLimitSnapshot }>('/api/v1/flickr/rate-limit');
    const snapshot = data?.data ?? null;
    const [selectedNsid, setSelectedNsidState] = useState<string | null>(null);

    const setSelectedNsid = useCallback((nsid: string) => {
        setSelectedNsidState(nsid);
        writeStoredFlickrQuotaNsid(nsid);
    }, []);

    useEffect(() => {
        if (!snapshot) {
            return;
        }

        setSelectedNsidState((current) => {
            if (current && snapshot.accounts.some((row) => row.account.nsid === current)) {
                return current;
            }

            return resolveDefaultFlickrQuotaNsid(
                snapshot.accounts.map((row) => row.account),
                snapshot.active_connection_key,
            );
        });
    }, [snapshot]);

    const selectedRateLimit = useMemo((): RateLimitState | null => {
        if (!snapshot || !selectedNsid) {
            return null;
        }

        return snapshot.accounts.find((row) => row.account.nsid === selectedNsid)?.rate_limit ?? null;
    }, [snapshot, selectedNsid]);

    const selectedCatalogCounts = useMemo((): FlickrCatalogCounts | null => {
        if (!snapshot || !selectedNsid) {
            return null;
        }

        return snapshot.accounts.find((row) => row.account.nsid === selectedNsid)?.catalog_counts ?? null;
    }, [snapshot, selectedNsid]);

    return {
        snapshot,
        selectedNsid,
        setSelectedNsid,
        selectedRateLimit,
        selectedCatalogCounts,
        loading,
    };
}

export function flickrAccountLabel(account: {
    fullname: string | null;
    username: string | null;
    nsid: string;
}): string {
    return account.fullname || account.username || account.nsid;
}
