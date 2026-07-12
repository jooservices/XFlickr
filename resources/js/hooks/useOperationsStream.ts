import { useCallback, useEffect, useState } from 'react';

import { apiGet } from '@/lib/apiClient';
import type { CrawlRun, TransferBatch } from '@/types';

export type DownloadTransferBatch = TransferBatch & { sample_error?: string | null };

export interface OperationsSnapshot {
    fetch_runs: CrawlRun[];
    download_batches: DownloadTransferBatch[];
    upload_batches: TransferBatch[];
}

function applySnapshot(snapshot: OperationsSnapshot): {
    fetchRuns: CrawlRun[];
    downloadBatches: DownloadTransferBatch[];
    uploadBatches: TransferBatch[];
} {
    return {
        fetchRuns: snapshot.fetch_runs ?? [],
        downloadBatches: snapshot.download_batches ?? [],
        uploadBatches: snapshot.upload_batches ?? [],
    };
}

export function useOperationsStream() {
    const [fetchRuns, setFetchRuns] = useState<CrawlRun[]>([]);
    const [downloadBatches, setDownloadBatches] = useState<DownloadTransferBatch[]>([]);
    const [uploadBatches, setUploadBatches] = useState<TransferBatch[]>([]);
    const [loading, setLoading] = useState(true);

    const apply = useCallback((snapshot: OperationsSnapshot) => {
        const next = applySnapshot(snapshot);
        setFetchRuns(next.fetchRuns);
        setDownloadBatches(next.downloadBatches);
        setUploadBatches(next.uploadBatches);
        setLoading(false);
    }, []);

    useEffect(() => {
        let closed = false;
        let source: EventSource | null = null;
        let pollTimer: ReturnType<typeof setInterval> | null = null;
        let reconnectTimer: ReturnType<typeof setTimeout> | null = null;

        const loadSnapshot = async () => {
            try {
                const snapshot = await apiGet<{ data: OperationsSnapshot }>('/api/v1/operations/snapshot');
                if (!closed) {
                    apply(snapshot.data);
                }
            } catch {
                // Ignore transient polling errors.
            }
        };

        const startPolling = () => {
            if (pollTimer !== null) {
                return;
            }

            void loadSnapshot();
            pollTimer = setInterval(() => {
                void loadSnapshot();
            }, 5000);
        };

        const stopPolling = () => {
            if (pollTimer !== null) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        };

        const connectStream = () => {
            if (closed || typeof EventSource === 'undefined') {
                startPolling();

                return;
            }

            source = new EventSource('/api/v1/operations/stream');

            source.addEventListener('operations', (event) => {
                try {
                    const snapshot = JSON.parse((event as MessageEvent<string>).data) as OperationsSnapshot;
                    apply(snapshot);
                    stopPolling();
                } catch {
                    source?.close();
                    source = null;
                    startPolling();
                }
            });

            source.onerror = () => {
                if (closed) {
                    return;
                }

                source?.close();
                source = null;
                startPolling();

                reconnectTimer = setTimeout(() => {
                    if (!closed && pollTimer !== null) {
                        stopPolling();
                        connectStream();
                    }
                }, 10_000);
            };
        };

        connectStream();

        return () => {
            closed = true;
            source?.close();

            if (pollTimer !== null) {
                clearInterval(pollTimer);
            }

            if (reconnectTimer !== null) {
                clearTimeout(reconnectTimer);
            }
        };
    }, [apply]);

    return {
        fetchRuns,
        downloadBatches,
        uploadBatches,
        loading,
    };
}
