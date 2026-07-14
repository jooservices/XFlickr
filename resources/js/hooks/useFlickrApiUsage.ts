import { usePolledResource } from '@/hooks/usePolledResource';
import type { FlickrApiUsageSnapshot } from '@/types';

const POLL_INTERVAL_MS = 5000;

export function useFlickrApiUsage(connectionKey: string | null, hours = 24) {
    const enabled = connectionKey !== null;
    const { data, loading } = usePolledResource<{ data: FlickrApiUsageSnapshot }>(
        enabled ? '/api/v1/flickr/rate-limit/usage' : null,
        {
            intervalMs: POLL_INTERVAL_MS,
            enabled,
            params: enabled
                ? {
                      connection_key: connectionKey,
                      hours,
                  }
                : undefined,
        },
    );

    return { snapshot: data?.data ?? null, loading: enabled && loading };
}
