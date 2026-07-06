import { useCallback, useEffect, useMemo, useState } from 'react';

import { apiGet } from '@/lib/apiClient';
import {
    resolveDefaultFlickrQuotaNsid,
    writeStoredFlickrQuotaNsid,
} from '@/lib/flickrQuotaAccount';
import type { FlickrRateLimitSnapshot, RateLimitState } from '@/types';

const POLL_INTERVAL_MS = 5000;

export function useFlickrRateLimit() {
    const [snapshot, setSnapshot] = useState<FlickrRateLimitSnapshot | null>(null);
    const [selectedNsid, setSelectedNsidState] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);

    const setSelectedNsid = useCallback((nsid: string) => {
        setSelectedNsidState(nsid);
        writeStoredFlickrQuotaNsid(nsid);
    }, []);

    useEffect(() => {
        const controller = new AbortController();

        const poll = () => {
            setLoading(true);
            void apiGet<{ data: FlickrRateLimitSnapshot }>('/api/flickr/rate-limit', {
                signal: controller.signal,
            })
                .then((json) => {
                    setSnapshot(json.data);

                    setSelectedNsidState((current) => {
                        if (current && json.data.accounts.some((row) => row.account.nsid === current)) {
                            return current;
                        }

                        return resolveDefaultFlickrQuotaNsid(
                            json.data.accounts.map((row) => row.account),
                            json.data.active_connection_key,
                        );
                    });
                })
                .catch(() => undefined)
                .finally(() => setLoading(false));
        };

        poll();
        const interval = setInterval(poll, POLL_INTERVAL_MS);

        return () => {
            controller.abort();
            clearInterval(interval);
        };
    }, []);

    const selectedRateLimit = useMemo((): RateLimitState | null => {
        if (!snapshot || !selectedNsid) {
            return null;
        }

        return snapshot.accounts.find((row) => row.account.nsid === selectedNsid)?.rate_limit ?? null;
    }, [snapshot, selectedNsid]);

    return {
        snapshot,
        selectedNsid,
        setSelectedNsid,
        selectedRateLimit,
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
