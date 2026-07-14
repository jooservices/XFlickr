import OperationStatusCell from '@/Components/ui/OperationStatusCell';
import type { ContactDownloadState } from '@/types';

interface ContactDownloadCellProps {
    count: number;
    failedCount: number;
    state?: ContactDownloadState;
}

export default function ContactDownloadCell({ count, failedCount, state }: ContactDownloadCellProps) {
    if (state?.processing) {
        const completed = state.batch_completed ?? count;

        return (
            <OperationStatusCell
                completed={completed}
                total={state.batch_total ?? null}
                label="Downloading…"
            />
        );
    }

    return (
        <div className="space-y-0.5">
            <span className="tabular-nums text-slate-600">{count}</span>
            {failedCount > 0 ? <p className="text-xs text-red-600">{failedCount} failed</p> : null}
        </div>
    );
}
