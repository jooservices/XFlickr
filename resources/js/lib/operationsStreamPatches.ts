import type {
    CrawlRun,
    OperationsActivityPoint,
    OperationsOverviewTotals,
    OperationsSnapshotPayload,
    TransferBatch,
} from '@/types';

export type DownloadTransferBatch = TransferBatch & {
    sample_error?: string | null;
    pending_count?: number;
    processing_count?: number;
};

export type OperationsStreamState = {
    overview: OperationsOverviewTotals;
    queues: OperationsSnapshotPayload['queues'];
    targetBreakdown: OperationsSnapshotPayload['target_breakdown'];
    spider: OperationsSnapshotPayload['spider'];
    dependencies: OperationsSnapshotPayload['dependencies'];
    databases: OperationsSnapshotPayload['databases'] | null;
    accounts: OperationsSnapshotPayload['accounts'];
    fetchRuns: CrawlRun[];
    downloadBatches: DownloadTransferBatch[];
    uploadBatches: TransferBatch[];
    activityHistory: OperationsActivityPoint[];
    loading: boolean;
    transport: 'websocket' | 'polling' | 'connecting';
};

export const EMPTY_OVERVIEW: OperationsOverviewTotals = {
    runs_running: 0,
    pending_targets: 0,
    downloads_active: 0,
    uploads_active: 0,
    failed_transfers_24h: 0,
    accounts_in_cooldown: 0,
    global_pause: false,
};

export const EMPTY_PROBE = { ok: false, latency_ms: null, detail: null };

export const EMPTY_DEPENDENCIES = {
    mysql: EMPTY_PROBE,
    redis: EMPTY_PROBE,
    mongodb: EMPTY_PROBE,
};

const ACTIVITY_HISTORY_MAX = 360;

function normalizeOverview(overview: Partial<OperationsOverviewTotals> | null | undefined): OperationsOverviewTotals {
    const globalPause = overview?.global_pause;
    return {
        runs_running: overview?.runs_running ?? 0,
        pending_targets: overview?.pending_targets ?? 0,
        downloads_active: overview?.downloads_active ?? 0,
        uploads_active: overview?.uploads_active ?? 0,
        failed_transfers_24h: overview?.failed_transfers_24h ?? 0,
        accounts_in_cooldown: overview?.accounts_in_cooldown ?? 0,
        global_pause: globalPause === true || globalPause === 1 || globalPause === '1',
    };
}

export function appendActivityPoint(
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

export function applySnapshot(
    previous: OperationsStreamState,
    snapshot: OperationsSnapshotPayload,
): OperationsStreamState {
    const overview = normalizeOverview(snapshot.overview);

    return {
        ...previous,
        overview,
        queues: snapshot.queues ?? {},
        targetBreakdown: snapshot.target_breakdown ?? [],
        spider: snapshot.spider ?? [],
        dependencies: snapshot.dependencies ?? EMPTY_DEPENDENCIES,
        databases: snapshot.databases ?? null,
        accounts: snapshot.accounts ?? [],
        fetchRuns: snapshot.fetch_runs ?? [],
        downloadBatches: (snapshot.download_batches ?? []) as DownloadTransferBatch[],
        uploadBatches: snapshot.upload_batches ?? [],
        activityHistory: appendActivityPoint(previous.activityHistory, overview),
        loading: false,
    };
}

function isNewerBatchPatch<T extends TransferBatch>(current: T, next: T): boolean {
    const currentAt = current.updated_at ? Date.parse(current.updated_at) : 0;
    const nextAt = next.updated_at ? Date.parse(next.updated_at) : Number.NaN;

    if (Number.isFinite(nextAt) && nextAt > 0) {
        if (!Number.isFinite(currentAt) || currentAt <= 0) {
            return true;
        }

        return nextAt >= currentAt;
    }

    const currentDone = current.completed_count + current.failed_count;
    const nextDone = next.completed_count + next.failed_count;

    return nextDone >= currentDone;
}

function upsertBatch<T extends TransferBatch>(
    rows: T[],
    batch: TransferBatch & { sample_error?: string | null },
): T[] {
    const next = { ...batch } as T;
    const index = rows.findIndex((row) => row.id === next.id);
    if (index === -1) {
        return [next, ...rows].slice(0, 40);
    }

    const current = rows[index];
    if (!isNewerBatchPatch(current, next)) {
        return rows;
    }

    const copy = [...rows];
    copy[index] = { ...current, ...next };
    return copy;
}

export function applyBatchPatch(
    previous: OperationsStreamState,
    batch: TransferBatch & { sample_error?: string | null },
): OperationsStreamState {
    if (! batch?.id) {
        return previous;
    }

    if (batch.type === 'upload') {
        return {
            ...previous,
            uploadBatches: upsertBatch(previous.uploadBatches, batch),
            loading: false,
        };
    }

    return {
        ...previous,
        downloadBatches: upsertBatch(previous.downloadBatches, batch),
        loading: false,
    };
}

export function applyOverviewPatch(
    previous: OperationsStreamState,
    overview: Partial<OperationsOverviewTotals>,
    queues?: OperationsSnapshotPayload['queues'],
): OperationsStreamState {
    const nextOverview = normalizeOverview({ ...previous.overview, ...overview });

    return {
        ...previous,
        overview: nextOverview,
        queues: queues ?? previous.queues,
        activityHistory: appendActivityPoint(previous.activityHistory, nextOverview),
        loading: false,
    };
}

export function applyUnknownPatch(previous: OperationsStreamState): OperationsStreamState {
    return previous;
}
