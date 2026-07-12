import { useOperationsStream } from '@/hooks/useOperationsStream';

export type { DownloadTransferBatch } from '@/hooks/useOperationsStream';

export function useCrawlOperations() {
    return useOperationsStream();
}
