import { AlertCircle, Loader2 } from 'lucide-react';

import type { PhotoDownloadStatus } from '@/types';

interface PhotoDownloadedCellProps {
    status?: PhotoDownloadStatus;
    viewUrl?: string | null;
}

export default function PhotoDownloadedCell({ status, viewUrl }: PhotoDownloadedCellProps) {
    if (status === 'downloading') {
        return (
            <div className="flex items-center gap-1.5 text-blue-700">
                <Loader2 className="size-3.5 shrink-0 animate-spin" />
                <span className="text-xs">Downloading…</span>
            </div>
        );
    }

    if (status === 'completed' && viewUrl) {
        return (
            <a href={viewUrl} target="_blank" rel="noreferrer" className="text-sm font-medium text-cyan-700 hover:underline">
                View
            </a>
        );
    }

    if (status === 'failed') {
        return (
            <span className="inline-flex items-center gap-1 text-xs text-red-600">
                <AlertCircle className="size-3.5 shrink-0" />
                Failed
            </span>
        );
    }

    return <span className="text-slate-400">—</span>;
}
