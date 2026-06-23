import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

import { apiGet } from '@/lib/apiClient';
import { flickrApiAccountPath } from '@/lib/flickrAccount';
import type { CrawlRun, FlickrAccount, PaginatedMeta, TransferBatch } from '@/types';

export type DownloadTransferBatch = TransferBatch & { sample_error?: string | null };

interface RunsResponse {
    data: CrawlRun[];
    meta: PaginatedMeta;
}

interface TransfersResponse {
    data: DownloadTransferBatch[];
}

export function useCrawlOperations(accounts: FlickrAccount[]) {
    const [fetchRuns, setFetchRuns] = useState<CrawlRun[]>([]);
    const [downloadBatches, setDownloadBatches] = useState<DownloadTransferBatch[]>([]);
    const [uploadBatches, setUploadBatches] = useState<TransferBatch[]>([]);
    const [loading, setLoading] = useState(true);

    const loadOperations = useCallback(async () => {
        const runs: CrawlRun[] = [];
        const downloads: DownloadTransferBatch[] = [];
        const uploads: TransferBatch[] = [];

        await Promise.all(
            accounts.map(async (account) => {
                const [runsResult, downloadsResult, uploadsResult] = await Promise.allSettled([
                    apiGet<RunsResponse>(flickrApiAccountPath(account.public_id, '/crawl/runs'), {
                        params: { per_page: 10 },
                    }),
                    apiGet<TransfersResponse>(flickrApiAccountPath(account.public_id, '/transfers'), {
                        params: { active: 1, type: 'download', limit: 20 },
                    }),
                    apiGet<TransfersResponse>(flickrApiAccountPath(account.public_id, '/transfers'), {
                        params: { active: 1, type: 'upload', limit: 20 },
                    }),
                ]);

                if (runsResult.status === 'fulfilled') {
                    runs.push(...runsResult.value.data.filter((run) => run.status === 'running'));
                }

                if (downloadsResult.status === 'fulfilled') {
                    downloads.push(...downloadsResult.value.data);
                }

                if (uploadsResult.status === 'fulfilled') {
                    uploads.push(...uploadsResult.value.data);
                }
            }),
        );

        setFetchRuns(runs);
        setDownloadBatches(downloads);
        setUploadBatches(uploads);
        setLoading(false);
    }, [accounts]);

    useEffect(() => {
        void loadOperations();

        const interval = setInterval(() => {
            router.reload({ only: ['accounts'] });
            void loadOperations();
        }, 5000);

        return () => clearInterval(interval);
    }, [loadOperations]);

    return {
        fetchRuns,
        downloadBatches,
        uploadBatches,
        loading,
    };
}
