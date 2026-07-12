import { useEffect, useState } from 'react';

import { apiGet } from '@/lib/apiClient';
import type { FlickrApiUsageSnapshot } from '@/types';

const POLL_INTERVAL_MS = 5000;

export function useFlickrApiUsage(connectionKey: string | null, hours = 24) {
    const [snapshot, setSnapshot] = useState<FlickrApiUsageSnapshot | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (!connectionKey) {
            setSnapshot(null);
            setLoading(false);

            return;
        }

        const controller = new AbortController();

        const poll = () => {
            setLoading(true);
            void apiGet<{ data: FlickrApiUsageSnapshot }>('/api/v1/flickr/rate-limit/usage', {
                signal: controller.signal,
                params: {
                    connection_key: connectionKey,
                    hours,
                },
            })
                .then((json) => setSnapshot(json.data))
                .catch(() => undefined)
                .finally(() => setLoading(false));
        };

        poll();
        const interval = setInterval(poll, POLL_INTERVAL_MS);

        return () => {
            controller.abort();
            clearInterval(interval);
        };
    }, [connectionKey, hours]);

    return { snapshot, loading };
}
