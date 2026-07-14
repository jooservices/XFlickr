import { Loader2 } from 'lucide-react';
import type { ReactNode } from 'react';

import ProgressBar from '@/Components/ui/ProgressBar';

export interface OperationStatusCellProps {
    completed: number;
    total?: number | null;
    label: string;
    hint?: ReactNode;
}

export default function OperationStatusCell({ completed, total = null, label, hint }: OperationStatusCellProps) {
    const progressMax = total ?? Math.max(completed, 1);

    return (
        <div className="min-w-24 space-y-1">
            <div className="flex items-center gap-1.5 text-cyan-800">
                <Loader2 className="size-3 shrink-0 animate-spin" aria-hidden />
                <span className="tabular-nums text-sm font-medium">
                    {total !== null && total !== undefined ? `${completed} / ${total}` : completed}
                </span>
            </div>
            <ProgressBar value={completed} max={progressMax} showLabel={false} />
            <p className="text-xs text-cyan-700">{label}</p>
            {hint ? <div className="text-xs text-slate-500">{hint}</div> : null}
        </div>
    );
}
