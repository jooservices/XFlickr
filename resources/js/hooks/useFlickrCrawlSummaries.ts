import { useCallback, useEffect, useState } from 'react';

import { apiGet } from '@/lib/apiClient';
import { flickrApiAccountPath } from '@/lib/flickrAccount';
import type { CrawlSummary } from '@/types';

interface AccountRef {
    nsid: string;
    public_id: string;
    is_connected?: boolean;
}

export function useFlickrCrawlSummaries(accounts: AccountRef[], pollMs = 10000): Record<string, CrawlSummary> {
    const [summaries, setSummaries] = useState<Record<string, CrawlSummary>>({});

    const loadSummaries = useCallback(async () => {
        const targets = accounts.filter((account) => account.is_connected !== false);

        const entries = await Promise.all(
            targets.map(async (account) => {
                try {
                    const data = await apiGet<{ data: CrawlSummary }>(
                        flickrApiAccountPath(account.public_id, '/crawl/summary'),
                    );

                    return [account.nsid, data.data] as const;
                } catch {
                    return [account.nsid, null] as const;
                }
            }),
        );

        setSummaries(
            Object.fromEntries(entries.filter(([, summary]) => summary !== null).map(([nsid, summary]) => [nsid, summary!])),
        );
    }, [accounts]);

    useEffect(() => {
        void loadSummaries();
        const interval = setInterval(() => void loadSummaries(), pollMs);

        return () => clearInterval(interval);
    }, [loadSummaries, pollMs]);

    return summaries;
}
