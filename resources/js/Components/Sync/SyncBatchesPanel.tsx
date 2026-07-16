import { useEffect, useMemo, useState } from 'react';

import PhotoDetailModal from '@/Components/Catalog/PhotoDetailModal';
import FlickrPhotoIdLinks from '@/Components/Flickr/PhotoIdLinks';
import SyncDetailDrawer from '@/Components/Sync/SyncDetailDrawer';
import Button from '@/Components/ui/Button';
import DataTable from '@/Components/ui/DataTable';
import PageSection from '@/Components/ui/PageSection';
import StatusBadge from '@/Components/ui/StatusBadge';
import Thumbnail from '@/Components/ui/Thumbnail';
import { usePolledResource } from '@/hooks/usePolledResource';
import { apiPost } from '@/lib/apiClient';
import { downloadGroupLabel } from '@/lib/crawlOperations';
import { flickrPhotoGridUrl } from '@/lib/flickrPhoto';
import type { FlickrAccount, PaginatedMeta, Photo, TransferBatch, TransferHistoryItem } from '@/types';

interface SyncBatchesPanelProps {
    accounts: FlickrAccount[];
}

export default function SyncBatchesPanel({ accounts }: SyncBatchesPanelProps) {
    const [selectedAccount, setSelectedAccount] = useState<string>(() => accounts[0]?.public_id || '');
    const [type, setType] = useState<'all' | 'download' | 'upload'>('all');
    const [status, setStatus] = useState<'all' | 'pending' | 'processing' | 'completed' | 'failed'>('all');
    const [page, setPage] = useState(1);
    const [selectedBatch, setSelectedBatch] = useState<TransferBatch | null>(null);
    const [selectedPhoto, setSelectedPhoto] = useState<Photo | null>(null);
    const [retryingItemId, setRetryingItemId] = useState<number | null>(null);

    const accountByPublicId = useMemo(
        () => Object.fromEntries(accounts.map((account) => [account.public_id, account])),
        [accounts],
    );

    useEffect(() => {
        setPage(1);
    }, [selectedAccount, type, status]);

    const listUrl = useMemo(() => {
        if (!selectedAccount) {
            return null;
        }

        const params = new URLSearchParams({ page: String(page), limit: '15' });
        if (type !== 'all') params.append('type', type);
        if (status !== 'all') params.append('status', status);

        return `/api/v1/flickr/accounts/${selectedAccount}/transfers/items?${params.toString()}`;
    }, [selectedAccount, type, status, page]);

    const { data, loading } = usePolledResource<{ data: TransferHistoryItem[]; meta: PaginatedMeta }>(listUrl, {
        intervalMs: 5000,
        enabled: Boolean(listUrl),
    });

    const items = useMemo(() => data?.data ?? [], [data]);
    const meta = data?.meta ?? null;
    const activeAccount = accountByPublicId[selectedAccount];
    const selectClassName = 'rounded-md border border-slate-200 bg-white px-2 py-1 text-xs text-slate-800';

    const retryItem = async (item: TransferHistoryItem) => {
        setRetryingItemId(item.id);
        try {
            await apiPost(
                `/api/v1/flickr/accounts/${selectedAccount}/transfers/${item.batch.id}/items/${item.flickr_photo_id}/retries`,
            );
        } finally {
            setRetryingItemId(null);
        }
    };

    return (
        <div data-testid="sync-batches-panel">
            <PageSection
                title="Transfer History"
                description="Download and upload history, one row per photo transfer."
            >
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                    {accounts.length > 1 ? (
                        <div className="flex items-center gap-2">
                            <label htmlFor="account-select" className="text-sm font-medium text-slate-700">
                                Flickr account
                            </label>
                            <select
                                id="account-select"
                                value={selectedAccount}
                                onChange={(event) => setSelectedAccount(event.target.value)}
                                className="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-800"
                            >
                                {accounts.map((account) => (
                                    <option key={account.public_id} value={account.public_id}>
                                        {account.fullname || account.username || account.nsid}
                                    </option>
                                ))}
                            </select>
                        </div>
                    ) : (
                        <div className="text-sm text-slate-600">
                            Monitoring <span className="font-medium text-slate-900">{activeAccount?.fullname || activeAccount?.username}</span>
                        </div>
                    )}

                    <div className="flex flex-wrap items-center gap-3">
                        <div className="flex items-center gap-1.5">
                            <span className="text-xs text-slate-500">Operation</span>
                            <select value={type} onChange={(event) => setType(event.target.value as typeof type)} className={selectClassName}>
                                <option value="all">All</option>
                                <option value="download">Downloads</option>
                                <option value="upload">Uploads</option>
                            </select>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <span className="text-xs text-slate-500">Status</span>
                            <select value={status} onChange={(event) => setStatus(event.target.value as typeof status)} className={selectClassName}>
                                <option value="all">All</option>
                                <option value="pending">Queued</option>
                                <option value="processing">Processing</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <DataTable
                    busy={loading && items.length === 0}
                    busyLabel="Loading transfer history…"
                    meta={meta ?? undefined}
                    onPageChange={setPage}
                    columns={[
                        {
                            key: 'batch',
                            label: 'Batch',
                            render: (item) => <span className="font-mono text-xs">#{item.batch.id}</span>,
                        },
                        {
                            key: 'thumbnail',
                            label: 'Thumbnail',
                            render: (item) => (
                                <Thumbnail
                                    url={item.photo ? flickrPhotoGridUrl(item.photo) : null}
                                    alt={item.photo?.title || `Photo ${item.flickr_photo_id}`}
                                    size="sm"
                                    onClick={item.photo ? () => setSelectedPhoto(item.photo) : undefined}
                                />
                            ),
                        },
                        {
                            key: 'photo',
                            label: 'Photo ID',
                            render: (item) => item.photo ? (
                                <FlickrPhotoIdLinks
                                    photoId={item.photo.flickr_photo_id}
                                    ownerNsid={item.photo.owner_nsid}
                                    title={item.photo.title}
                                />
                            ) : <span className="font-mono text-xs">{item.flickr_photo_id}</span>,
                        },
                        {
                            key: 'group',
                            label: 'Target Label / Group',
                            render: (item) => downloadGroupLabel(item.batch),
                        },
                        {
                            key: 'operation',
                            label: 'Operation',
                            render: (item) => <span className="capitalize">{item.batch.type}</span>,
                        },
                        {
                            key: 'status',
                            label: 'Status',
                            render: (item) => <StatusBadge status={item.status} />,
                        },
                        {
                            key: 'queued',
                            label: 'Date Queued',
                            render: (item) => item.created_at ? new Date(item.created_at).toLocaleString() : '—',
                        },
                        {
                            key: 'actions',
                            label: '',
                            render: (item) => (
                                <div className="flex items-center gap-1">
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setSelectedBatch(item.batch)}>
                                        Inspect
                                    </Button>
                                    {item.status === 'failed' ? (
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            disabled={retryingItemId === item.id}
                                            onClick={() => void retryItem(item)}
                                        >
                                            {retryingItemId === item.id ? 'Retrying…' : 'Retry'}
                                        </Button>
                                    ) : null}
                                </div>
                            ),
                        },
                    ]}
                    data={items}
                    rowKey={(item) => String(item.id)}
                    emptyMessage="No transfer history found matching filters."
                />
            </PageSection>

            <SyncDetailDrawer
                batch={selectedBatch}
                connectionId={activeAccount?.public_id || ''}
                onClose={() => setSelectedBatch(null)}
            />
            <PhotoDetailModal
                photo={selectedPhoto}
                accountPublicId={activeAccount?.public_id}
                onClose={() => setSelectedPhoto(null)}
            />
        </div>
    );
}
