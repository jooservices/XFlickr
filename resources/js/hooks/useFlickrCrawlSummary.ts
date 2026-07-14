import { usePolledResource } from '@/hooks/usePolledResource';
import { flickrApiAccountPath } from '@/lib/flickrAccount';
import type { CrawlSummary } from '@/types';

export function useFlickrCrawlSummary(publicId: string, enabled = true, pollMs = 10_000): CrawlSummary | null {
    const { data } = usePolledResource<{ data: CrawlSummary }>(
        enabled ? flickrApiAccountPath(publicId, '/crawl/summary') : null,
        { intervalMs: pollMs, enabled },
    );

    return data?.data ?? null;
}
