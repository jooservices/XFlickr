import RateLimitMeter from '@/Components/Flickr/RateLimitMeter';
import type { CrawlSummary } from '@/types';

interface FlickrAccountCardFooterProps {
    summary: CrawlSummary;
}

export default function FlickrAccountCardFooter({ summary }: FlickrAccountCardFooterProps) {
    return (
        <div className="space-y-1">
            <RateLimitMeter rateLimit={summary.rate_limit} compact />
            <p className="text-xs text-slate-500">
                Running: {summary.runs.running} · Pending: {summary.pending_targets}
            </p>
        </div>
    );
}
