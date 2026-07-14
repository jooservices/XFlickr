import { useCallback, useEffect, useState } from 'react';

import { usePolledResource } from '@/hooks/usePolledResource';
import type {
    CrawlRun,
    DatabaseUsageSnapshot,
    OperationsAccountRow,
    OperationsActivityPoint,
    OperationsOverviewTotals,
    OperationsSnapshotPayload,
    ServiceDependencyProbe,
    TransferBatch,
} from '@/types';

export type DownloadTransferBatch = TransferBatch & { sample_error?: string | null };

const EMPTY_OVERVIEW: OperationsOverviewTotals = {
    runs_running: 0,
    pending_targets: 0,
    downloads_active: 0,
    uploads_active: 0,
    failed_transfers_24h: 0,
    accounts_in_cooldown: 0,
};

const EMPTY_PROBE: ServiceDependencyProbe = { ok: false, latency_ms: null, detail: null };

const EMPTY_DEPENDENCIES = {
    mysql: EMPTY_PROBE,
    redis: EMPTY_PROBE,
    mongodb: EMPTY_PROBE,
};

/** Poll interval — short JSON requests; avoid long-lived SSE on single-worker PHP. */
const POLL_INTERVAL_MS = 5000;

/** ~30 minutes of activity samples at 5s polling. */
const ACTIVITY_HISTORY_MAX = 360;

function applySnapshot(snapshot: OperationsSnapshotPayload): {
    overview: OperationsOverviewTotals;
    dependencies: OperationsSnapshotPayload['dependencies'];
    databases: DatabaseUsageSnapshot | null;
    accounts: OperationsAccountRow[];
    fetchRuns: CrawlRun[];
    downloadBatches: DownloadTransferBatch[];
    uploadBatches: TransferBatch[];
} {
    return {
        overview: snapshot.overview ?? EMPTY_OVERVIEW,
        dependencies: snapshot.dependencies ?? EMPTY_DEPENDENCIES,
        databases: snapshot.databases ?? null,
        accounts: snapshot.accounts ?? [],
        fetchRuns: snapshot.fetch_runs ?? [],
        downloadBatches: (snapshot.download_batches ?? []) as DownloadTransferBatch[],
        uploadBatches: snapshot.upload_batches ?? [],
    };
}

function appendActivityPoint(
    previous: OperationsActivityPoint[],
    overview: OperationsOverviewTotals,
): OperationsActivityPoint[] {
    const point: OperationsActivityPoint = {
        t: Math.floor(Date.now() / 1000),
        runs_running: overview.runs_running,
        pending_targets: overview.pending_targets,
        transfers_active: overview.downloads_active + overview.uploads_active,
    };

    const last = previous[previous.length - 1];
    if (
        last &&
        last.t === point.t &&
        last.runs_running === point.runs_running &&
        last.pending_targets === point.pending_targets &&
        last.transfers_active === point.transfers_active
    ) {
        return previous;
    }

    return [...previous, point].slice(-ACTIVITY_HISTORY_MAX);
}

/**
 * Operations sidebar + console live data.
 *
 * Uses JSON polling (via usePolledResource), not Server-Sent Events: long-lived SSE on
 * single-threaded `php artisan serve` blocked the whole app. This hook stays separate from
 * plain usePolledResource call sites because it folds each snapshot into activityHistory.
 */
export function useOperationsStream() {
    const [overview, setOverview] = useState<OperationsOverviewTotals>(EMPTY_OVERVIEW);
    const [dependencies, setDependencies] =
        useState<OperationsSnapshotPayload['dependencies']>(EMPTY_DEPENDENCIES);
    const [databases, setDatabases] = useState<DatabaseUsageSnapshot | null>(null);
    const [accounts, setAccounts] = useState<OperationsAccountRow[]>([]);
    const [fetchRuns, setFetchRuns] = useState<CrawlRun[]>([]);
    const [downloadBatches, setDownloadBatches] = useState<DownloadTransferBatch[]>([]);
    const [uploadBatches, setUploadBatches] = useState<TransferBatch[]>([]);
    const [activityHistory, setActivityHistory] = useState<OperationsActivityPoint[]>([]);
    const [loading, setLoading] = useState(true);

    const { data } = usePolledResource<{ data: OperationsSnapshotPayload }>('/api/v1/operations/snapshot', {
        intervalMs: POLL_INTERVAL_MS,
    });

    const apply = useCallback((snapshot: OperationsSnapshotPayload) => {
        const next = applySnapshot(snapshot);
        setOverview(next.overview);
        setDependencies(next.dependencies);
        setDatabases(next.databases);
        setAccounts(next.accounts);
        setFetchRuns(next.fetchRuns);
        setDownloadBatches(next.downloadBatches);
        setUploadBatches(next.uploadBatches);
        setActivityHistory((previous) => appendActivityPoint(previous, next.overview));
        setLoading(false);
    }, []);

    useEffect(() => {
        if (!data?.data) {
            return;
        }

        apply(data.data);
    }, [apply, data]);

    return {
        overview,
        dependencies,
        databases,
        accounts,
        fetchRuns,
        downloadBatches,
        uploadBatches,
        activityHistory,
        loading,
    };
}
