import { Link } from '@inertiajs/react';
import { ArrowDownToLine, ArrowUpFromLine, Loader2, RefreshCw } from 'lucide-react';
import { useMemo } from 'react';

import ProgressBar from '@/Components/ProgressBar';
import type { DownloadTransferBatch } from '@/hooks/useOperationsStream';
import { cn } from '@/lib/cn';
import { accountLabel, downloadGroupLabel } from '@/lib/crawlOperations';
import type { CrawlRun, FlickrAccount, TransferBatch } from '@/types';

const MAX_ROWS = 5;

type ActivityRow = {
    key: string;
    kind: 'fetch' | 'download' | 'upload';
    label: string;
    detail: string;
    value: number;
    max: number;
};

interface SidebarActivityPanelProps {
    fetchRuns: CrawlRun[];
    downloadBatches: DownloadTransferBatch[];
    uploadBatches: TransferBatch[];
    loading: boolean;
    accountByNsid: Record<string, FlickrAccount>;
}

function buildActivityRows(
    fetchRuns: CrawlRun[],
    downloadBatches: DownloadTransferBatch[],
    uploadBatches: TransferBatch[],
    accountByNsid: Record<string, FlickrAccount>,
): ActivityRow[] {
    const rows: ActivityRow[] = [];

    for (const batch of downloadBatches) {
        const account = accountByNsid[batch.connection_key];
        const processed = batch.completed_count + batch.failed_count;
        const max = Math.max(batch.total_count, processed, 1);

        rows.push({
            key: `download-${batch.id}`,
            kind: 'download',
            label: 'Download',
            detail: `${accountLabel(account)} · ${downloadGroupLabel(batch)}`,
            value: processed,
            max,
        });
    }

    for (const batch of uploadBatches) {
        const account = accountByNsid[batch.connection_key];
        const processed = batch.completed_count + batch.failed_count;
        const max = Math.max(batch.total_count, processed, 1);

        rows.push({
            key: `upload-${batch.id}`,
            kind: 'upload',
            label: 'Upload',
            detail: accountLabel(account),
            value: processed,
            max,
        });
    }

    for (const run of fetchRuns) {
        const account = accountByNsid[run.connection_key];
        const discovered = run.photos_discovered + run.contacts_discovered;
        const max = Math.max(discovered, run.api_calls, 1);

        rows.push({
            key: `fetch-${run.id}`,
            kind: 'fetch',
            label: 'Fetch',
            detail: `${accountLabel(account)} · ${run.crawl_type}${run.subject_nsid ? ` · ${run.subject_nsid}` : ''}`,
            value: discovered,
            max,
        });
    }

    return rows.slice(0, MAX_ROWS);
}

function ActivityIcon({ kind }: { kind: ActivityRow['kind'] }) {
    if (kind === 'download') {
        return <ArrowDownToLine className="h-3.5 w-3.5 shrink-0 text-blue-600" aria-hidden />;
    }

    if (kind === 'upload') {
        return <ArrowUpFromLine className="h-3.5 w-3.5 shrink-0 text-violet-600" aria-hidden />;
    }

    return <RefreshCw className="h-3.5 w-3.5 shrink-0 text-cyan-600" aria-hidden />;
}

export default function SidebarActivityPanel({
    fetchRuns,
    downloadBatches,
    uploadBatches,
    loading,
    accountByNsid,
}: SidebarActivityPanelProps) {
    const rows = useMemo(
        () => buildActivityRows(fetchRuns, downloadBatches, uploadBatches, accountByNsid),
        [fetchRuns, downloadBatches, uploadBatches, accountByNsid],
    );

    const totalActive = fetchRuns.length + downloadBatches.length + uploadBatches.length;

    if (!loading && totalActive === 0) {
        return null;
    }

    return (
        <section aria-label="Live operations" className="shrink-0 border-t border-slate-200 p-3">
            <div className="flex items-center justify-between gap-2 px-3 pb-2">
                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Live</p>
                {loading ? (
                    <Loader2 className="h-3 w-3 animate-spin text-slate-400" aria-label="Loading operations" />
                ) : (
                    <span className="text-[11px] font-medium tabular-nums text-slate-500">{totalActive}</span>
                )}
            </div>

            {rows.length === 0 && loading ? (
                <p className="px-3 py-1 text-xs text-slate-500">Checking active jobs…</p>
            ) : (
                <ul className="space-y-2">
                    {rows.map((row) => (
                        <li key={row.key} className="rounded-md px-3 py-1.5">
                            <div className="flex items-center gap-2 text-xs font-medium text-slate-700">
                                <ActivityIcon kind={row.kind} />
                                <span>{row.label}</span>
                                <span className="ml-auto tabular-nums text-slate-500">
                                    {row.value}/{row.max}
                                </span>
                            </div>
                            <p className="mt-0.5 truncate pl-5 text-[11px] text-slate-500" title={row.detail}>
                                {row.detail}
                            </p>
                            <div className="mt-1 pl-5">
                                <ProgressBar value={row.value} max={row.max} showLabel={false} className="[&>div]:h-1.5" />
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {totalActive > MAX_ROWS ? (
                <p className="px-3 pt-1 text-[11px] text-slate-500">+{totalActive - MAX_ROWS} more</p>
            ) : null}

            <Link
                href="/crawl/operations"
                className={cn(
                    'mt-2 block rounded-md px-3 py-1.5 text-xs font-medium text-cyan-700 hover:bg-cyan-50',
                )}
            >
                View Operations
            </Link>
        </section>
    );
}
