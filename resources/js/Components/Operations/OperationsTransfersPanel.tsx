import { useMemo } from 'react';

import ContactNsidLinks from '@/Components/ContactNsidLinks';
import DataTable from '@/Components/DataTable';
import PageSection from '@/Components/PageSection';
import ProgressBar from '@/Components/ProgressBar';
import StatusBadge from '@/Components/StatusBadge';
import TransferBatchFailures from '@/Components/TransferBatchFailures';
import type { DownloadTransferBatch } from '@/hooks/useOperationsStream';
import { useTableSort } from '@/hooks/useTableSort';
import {
    accountLabel,
    downloadGroupLabel,
    downloadStoragePath,
    transferBatchSortValue,
} from '@/lib/crawlOperations';
import { sortClientData } from '@/lib/tableSort';
import type { FlickrAccount, TransferBatch } from '@/types';

interface OperationsTransfersPanelProps {
    downloadBatches: DownloadTransferBatch[];
    uploadBatches: TransferBatch[];
    accounts: FlickrAccount[];
    loading: boolean;
}

export default function OperationsTransfersPanel({
    downloadBatches,
    uploadBatches,
    accounts,
    loading,
}: OperationsTransfersPanelProps) {
    const downloadSort = useTableSort({ initialSort: 'id', initialDirection: 'desc' });
    const uploadSort = useTableSort({ initialSort: 'id', initialDirection: 'desc' });

    const accountByNsid = useMemo(
        () => Object.fromEntries(accounts.map((account) => [account.nsid, account])),
        [accounts],
    );

    const sortedDownloadBatches = useMemo(
        () =>
            sortClientData(downloadBatches, downloadSort.sortKey, downloadSort.sortDirection, (batch, key) =>
                transferBatchSortValue(batch, key, accountByNsid, downloadGroupLabel),
            ),
        [downloadBatches, downloadSort.sortKey, downloadSort.sortDirection, accountByNsid],
    );

    const sortedUploadBatches = useMemo(
        () =>
            sortClientData(uploadBatches, uploadSort.sortKey, uploadSort.sortDirection, (batch, key) =>
                transferBatchSortValue(batch, key, accountByNsid, () => ''),
            ),
        [uploadBatches, uploadSort.sortKey, uploadSort.sortDirection, accountByNsid],
    );

    return (
        <div className="space-y-8">
            <PageSection
                title="Download"
                description="Photo downloads from Flickr into local storage, grouped by owner NSID and photoset or gallery when known."
            >
                <DataTable
                    busy={loading}
                    busyLabel="Loading download batches…"
                    columns={[
                        {
                            key: 'id',
                            label: 'Batch',
                            sortable: true,
                            render: (batch) => <span className="font-mono text-xs">#{batch.id}</span>,
                        },
                        {
                            key: 'account',
                            label: 'Account',
                            sortable: true,
                            render: (batch) => accountLabel(accountByNsid[batch.connection_key]),
                        },
                        {
                            key: 'owner',
                            label: 'Owner',
                            sortable: true,
                            render: (batch) =>
                                batch.subject_nsid ? (
                                    <ContactNsidLinks
                                        nsid={batch.subject_nsid}
                                        accountPublicId={accountByNsid[batch.connection_key]?.public_id}
                                    />
                                ) : (
                                    '—'
                                ),
                        },
                        {
                            key: 'group',
                            label: 'Group',
                            sortable: true,
                            render: (batch) => downloadGroupLabel(batch),
                        },
                        {
                            key: 'storage',
                            label: 'Storage',
                            sortable: true,
                            render: (batch) => (
                                <span className="font-mono text-xs text-slate-500">
                                    {downloadStoragePath(batch)}
                                </span>
                            ),
                        },
                        {
                            key: 'progress',
                            label: 'Progress',
                            sortable: true,
                            className: 'w-48',
                            render: (batch) => {
                                const processed = batch.completed_count + batch.failed_count;
                                const max = Math.max(batch.total_count, 1);

                                return (
                                    <div>
                                        <ProgressBar value={processed} max={max} showLabel={false} />
                                        <p className="mt-1 text-xs text-slate-500">
                                            {batch.completed_count} completed
                                            {batch.failed_count > 0 ? ` · ${batch.failed_count} failed` : ''}
                                            {' · '}
                                            {batch.total_count} total
                                        </p>
                                        {'sample_error' in batch && batch.sample_error ? (
                                            <p
                                                className="mt-1 line-clamp-2 text-xs text-red-600"
                                                title={batch.sample_error}
                                            >
                                                {batch.sample_error}
                                            </p>
                                        ) : null}
                                        <TransferBatchFailures
                                            batch={batch}
                                            account={accountByNsid[batch.connection_key]}
                                        />
                                    </div>
                                );
                            },
                        },
                        {
                            key: 'status',
                            label: 'Status',
                            sortable: true,
                            render: (batch) => <StatusBadge status={batch.status} />,
                        },
                    ]}
                    data={sortedDownloadBatches}
                    rowKey={(batch) => String(batch.id)}
                    sortKey={downloadSort.sortKey}
                    sortDirection={downloadSort.sortDirection}
                    onSortChange={downloadSort.handleSortChange}
                    emptyMessage={
                        <>
                            No recent download batches. Downloads appear here when photos are queued and files land
                            under storage/app/private/flickr/&lt;nsid&gt;/photos/.
                        </>
                    }
                />
            </PageSection>

            <PageSection
                title="Upload"
                description="Photo uploads from local storage to configured storage accounts."
            >
                <DataTable
                    busy={loading}
                    busyLabel="Loading upload batches…"
                    columns={[
                        {
                            key: 'id',
                            label: 'Batch',
                            sortable: true,
                            render: (batch) => <span className="font-mono text-xs">#{batch.id}</span>,
                        },
                        {
                            key: 'account',
                            label: 'Account',
                            sortable: true,
                            render: (batch) => accountLabel(accountByNsid[batch.connection_key]),
                        },
                        {
                            key: 'subject',
                            label: 'Subject',
                            sortable: true,
                            render: (batch) =>
                                batch.subject_nsid ? (
                                    <ContactNsidLinks
                                        nsid={batch.subject_nsid}
                                        accountPublicId={accountByNsid[batch.connection_key]?.public_id}
                                    />
                                ) : (
                                    'All photos'
                                ),
                        },
                        {
                            key: 'progress',
                            label: 'Progress',
                            sortable: true,
                            className: 'w-48',
                            render: (batch) => {
                                const processed = batch.completed_count + batch.failed_count;
                                const max = Math.max(batch.total_count, 1);

                                return (
                                    <div>
                                        <ProgressBar value={processed} max={max} showLabel={false} />
                                        <p className="mt-1 text-xs text-slate-500">
                                            {batch.completed_count} completed
                                            {batch.failed_count > 0 ? ` · ${batch.failed_count} failed` : ''}
                                            {' · '}
                                            {batch.total_count} total
                                        </p>
                                        <TransferBatchFailures
                                            batch={batch}
                                            account={accountByNsid[batch.connection_key]}
                                        />
                                    </div>
                                );
                            },
                        },
                        {
                            key: 'status',
                            label: 'Status',
                            sortable: true,
                            render: (batch) => <StatusBadge status={batch.status} />,
                        },
                    ]}
                    data={sortedUploadBatches}
                    rowKey={(batch) => String(batch.id)}
                    sortKey={uploadSort.sortKey}
                    sortDirection={uploadSort.sortDirection}
                    onSortChange={uploadSort.handleSortChange}
                    emptyMessage="No active upload batches."
                />
            </PageSection>
        </div>
    );
}
