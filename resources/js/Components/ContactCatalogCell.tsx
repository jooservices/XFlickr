import { Loader2 } from 'lucide-react';

import ProgressBar from '@/Components/ProgressBar';
import type { CrawlTypeState } from '@/types';

interface ContactCatalogCellProps {
    count: number;
    state?: CrawlTypeState;
}

export default function ContactCatalogCell({ count, state }: ContactCatalogCellProps) {
    if (!state?.processing) {
        return <span className="tabular-nums text-slate-600">{count}</span>;
    }

    const fetched = state.fetched ?? count;
    const total = state.total ?? null;
    const progressMax = total ?? Math.max(fetched, 1);

    return (
        <div className="min-w-24 space-y-1">
            <div className="flex items-center gap-1.5 text-blue-800">
                <Loader2 className="size-3 shrink-0 animate-spin" />
                <span className="tabular-nums text-sm font-medium">
                    {total !== null ? `${fetched} / ${total}` : fetched}
                </span>
            </div>
            <ProgressBar value={fetched} max={progressMax} showLabel={false} />
            <p className="text-xs text-blue-600">Fetching…</p>
        </div>
    );
}
