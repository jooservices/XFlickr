import { Loader2 } from 'lucide-react';

import ProgressBar from '@/Components/ui/ProgressBar';
import type { ContactDownloadState } from '@/types';

interface ContactDownloadCellProps {
    count: number;
    failedCount: number;
    state?: ContactDownloadState;
}

export default function ContactDownloadCell({ count, failedCount, state }: ContactDownloadCellProps) {
    if (state?.processing) {
        const completed = state.batch_completed ?? count;
        const total = state.batch_total ?? null;
        const progressMax = total ?? Math.max(completed, 1);

        return (
            <div className="min-w-24 space-y-1">
                <div className="flex items-center gap-1.5 text-blue-800">
                    <Loader2 className="size-3 shrink-0 animate-spin" />
                    <span className="tabular-nums text-sm font-medium">
                        {total !== null && total !== undefined ? `${completed} / ${total}` : completed}
                    </span>
                </div>
                <ProgressBar value={completed} max={progressMax} showLabel={false} />
                <p className="text-xs text-blue-600">Downloading…</p>
            </div>
        );
    }

    return (
        <div className="space-y-0.5">
            <span className="tabular-nums text-slate-600">{count}</span>
            {failedCount > 0 ? <p className="text-xs text-red-600">{failedCount} failed</p> : null}
        </div>
    );
}
