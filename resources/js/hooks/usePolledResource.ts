import { useEffect, useRef, useState } from 'react';

import { apiGet } from '@/lib/apiClient';

export type UsePolledResourceOptions = {
    intervalMs?: number;
    enabled?: boolean;
    params?: Record<string, string | number | boolean | undefined | null>;
};

export type UsePolledResourceResult<T> = {
    data: T | null;
    error: Error | null;
    loading: boolean;
    refresh: () => void;
};

export function usePolledResource<T>(
    url: string | null,
    options: UsePolledResourceOptions = {},
): UsePolledResourceResult<T> {
    const { intervalMs = 5000, enabled = true, params } = options;
    const [data, setData] = useState<T | null>(null);
    const [error, setError] = useState<Error | null>(null);
    const [loading, setLoading] = useState(false);
    const [tick, setTick] = useState(0);
    const inFlightRef = useRef(false);
    const paramsRef = useRef(params);
    paramsRef.current = params;

    useEffect(() => {
        if (!url || !enabled) {
            return;
        }

        const controller = new AbortController();

        const poll = async () => {
            if (document.visibilityState === 'hidden' || inFlightRef.current) {
                return;
            }

            inFlightRef.current = true;
            setLoading(true);

            try {
                const next = await apiGet<T>(url, {
                    params: paramsRef.current,
                    signal: controller.signal,
                });
                setData(next);
                setError(null);
            } catch (err) {
                if (controller.signal.aborted) {
                    return;
                }

                setError(err instanceof Error ? err : new Error('Polling failed'));
            } finally {
                inFlightRef.current = false;
                setLoading(false);
            }
        };

        void poll();
        const timer = window.setInterval(() => {
            void poll();
        }, intervalMs);

        const onVisibility = () => {
            if (document.visibilityState === 'visible') {
                void poll();
            }
        };

        document.addEventListener('visibilitychange', onVisibility);

        return () => {
            controller.abort();
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [url, enabled, intervalMs, tick]);

    return {
        data,
        error,
        loading,
        refresh: () => setTick((value) => value + 1),
    };
}
