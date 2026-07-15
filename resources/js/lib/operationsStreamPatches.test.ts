import { describe, expect, it } from 'vitest';

import {
    EMPTY_DEPENDENCIES,
    EMPTY_OVERVIEW,
    applyBatchPatch,
    applyOverviewPatch,
    applySnapshot,
    applyUnknownPatch,
    type OperationsStreamState,
} from '@/lib/operationsStreamPatches';
import type { TransferBatch } from '@/types';

function emptyState(): OperationsStreamState {
    return {
        overview: EMPTY_OVERVIEW,
        queues: {},
        targetBreakdown: [],
        spider: [],
        dependencies: EMPTY_DEPENDENCIES,
        databases: null,
        accounts: [],
        fetchRuns: [],
        downloadBatches: [],
        uploadBatches: [],
        activityHistory: [],
        loading: true,
        transport: 'connecting',
    };
}

function batch(partial: Partial<TransferBatch> & Pick<TransferBatch, 'id'>): TransferBatch {
    return {
        type: 'download',
        connection_key: 'a@N01',
        subject_nsid: null,
        group_type: null,
        group_id: null,
        group_label: null,
        storage_account_id: null,
        status: 'running',
        total_count: 10,
        completed_count: 0,
        failed_count: 0,
        created_at: null,
        updated_at: null,
        ...partial,
    };
}

describe('operationsStreamPatches', () => {
    it('applies a full snapshot and clears loading', () => {
        const next = applySnapshot(emptyState(), {
            overview: {
                runs_running: 2,
                pending_targets: 5,
                downloads_active: 1,
                uploads_active: 0,
                failed_transfers_24h: 0,
                accounts_in_cooldown: 0,
                global_pause: 1,
            },
            queues: { xflickr: 3 },
            target_breakdown: [],
            spider: [],
            dependencies: EMPTY_DEPENDENCIES,
            databases: {
                mysql: {
                    status: 'ok',
                    driver: 'mysql',
                    database: 'xflickr',
                    size_bytes: 1,
                    connections_current: 1,
                    connections_max: 10,
                    tables: [],
                    error: null,
                },
                mongodb: {
                    status: 'ok',
                    driver: 'mongodb',
                    database: 'xflickr',
                    size_bytes: 1,
                    collections: 1,
                    objects: 1,
                    error: null,
                },
                history: [],
            },
            accounts: [],
            fetch_runs: [],
            download_batches: [],
            upload_batches: [],
        });

        expect(next.loading).toBe(false);
        expect(next.overview.runs_running).toBe(2);
        expect(next.overview.global_pause).toBe(true);
        expect(next.queues.xflickr).toBe(3);
        expect(next.activityHistory).toHaveLength(1);
    });

    it('upserts batch patches and ignores stale lower progress', () => {
        const withBatch = applyBatchPatch(emptyState(), {
            ...batch({ id: 9, completed_count: 4, failed_count: 0 }),
            sample_error: null,
        });
        expect(withBatch.downloadBatches[0]?.completed_count).toBe(4);

        const stale = applyBatchPatch(withBatch, {
            ...batch({ id: 9, completed_count: 2, failed_count: 0 }),
        });
        expect(stale.downloadBatches[0]?.completed_count).toBe(4);

        const newer = applyBatchPatch(withBatch, {
            ...batch({ id: 9, completed_count: 6, failed_count: 1, status: 'running' }),
            sample_error: 'boom',
        });
        expect(newer.downloadBatches[0]?.completed_count).toBe(6);
        expect(newer.downloadBatches[0]?.failed_count).toBe(1);
    });

    it('merges overview patches and no-ops unknown patches', () => {
        const next = applyOverviewPatch(emptyState(), { runs_running: 7 }, { xflickr: 1 });
        expect(next.overview.runs_running).toBe(7);
        expect(next.queues.xflickr).toBe(1);
        expect(applyUnknownPatch(next)).toBe(next);
    });
});
