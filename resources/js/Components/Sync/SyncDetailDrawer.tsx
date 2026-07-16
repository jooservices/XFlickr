import { RotateCw } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import PhotoDetailModal from '@/Components/Catalog/PhotoDetailModal';
import FlickrPhotoIdLinks from '@/Components/Flickr/PhotoIdLinks';
import Button from '@/Components/ui/Button';
import DataTable from '@/Components/ui/DataTable';
import Modal from '@/Components/ui/Modal';
import SegmentedControl from '@/Components/ui/SegmentedControl';
import StatusBadge from '@/Components/ui/StatusBadge';
import Thumbnail from '@/Components/ui/Thumbnail';
import { apiGet, apiPost } from '@/lib/apiClient';
import { flickrPhotoGridUrl } from '@/lib/flickrPhoto';
import type { Photo, TransferBatch, TransferItem } from '@/types';

interface SyncDetailDrawerProps {
    batch: TransferBatch | null;
    onClose: () => void;
    connectionId: string;
}

export default function SyncDetailDrawer({ batch, onClose, connectionId }: SyncDetailDrawerProps) {
    const [items, setItems] = useState<TransferItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [filter, setFilter] = useState<'all' | 'pending' | 'completed' | 'failed'>('all');
    const [retryingId, setRetryingId] = useState<string | null>(null);
    const [bulkRetrying, setBulkRetrying] = useState(false);
    const [selectedPhoto, setSelectedPhoto] = useState<Photo | null>(null);

    const loadDetails = useCallback(async () => {
        if (!batch) return;

        setLoading(true);
        try {
            const response = await apiGet<{ data: { batch: TransferBatch; items: TransferItem[] } }>(
                `/api/v1/flickr/accounts/${connectionId}/transfers/${batch.id}`,
            );
            setItems(response.data.items || []);
        } catch (err) {
            console.error('Failed to load batch details', err);
        } finally {
            setLoading(false);
        }
    }, [batch, connectionId]);

    useEffect(() => {
        if (batch) {
            void loadDetails();
        } else {
            setItems([]);
            setSelectedPhoto(null);
        }
    }, [batch, loadDetails]);

    if (!batch) return null;

    const retryIndividualItem = async (photoId: string) => {
        setRetryingId(photoId);
        try {
            await apiPost(
                `/api/v1/flickr/accounts/${connectionId}/transfers/${batch.id}/items/${photoId}/retries`,
            );
            setItems((current) =>
                current.map((item) =>
                    item.flickr_photo_id === photoId ? { ...item, status: 'pending', error_message: null } : item,
                ),
            );
        } catch (err) {
            console.error('Failed to retry item', err);
        } finally {
            setRetryingId(null);
        }
    };

    const retryAllFailed = async () => {
        setBulkRetrying(true);
        try {
            await apiPost(`/api/v1/flickr/accounts/${connectionId}/transfers/${batch.id}/retries`);
            void loadDetails();
        } catch (err) {
            console.error('Failed to retry all items', err);
        } finally {
            setBulkRetrying(false);
        }
    };

    const filteredItems = items.filter((item) => {
        if (filter === 'all') return true;

        return item.status === filter;
    });

    const completedCount = items.filter((item) => item.status === 'completed').length || batch.completed_count;
    const failedCount = items.filter((item) => item.status === 'failed').length || batch.failed_count;
    const pendingCount =
        items.filter((item) => item.status === 'pending').length ||
        batch.total_count - batch.completed_count - batch.failed_count;

    return (
        <>
        <Modal open onClose={onClose} titleId="sync-batch-detail-title" size="xl">
            <Modal.Header title={`Batch #${batch.id}`} />
            <Modal.Body className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
                    <p>
                        <span className="font-medium text-slate-900 dark:text-slate-100">
                            {batch.type === 'download' ? 'Download from Flickr' : 'Upload to storage'}
                        </span>
                        {' · '}
                        {batch.group_label || 'Bulk'}
                    </p>
                    <StatusBadge status={batch.status} />
                </div>

                <dl className="grid gap-3 text-sm sm:grid-cols-4">
                    <div className="border-b border-slate-100 pb-2">
                        <dt className="text-slate-500">Total</dt>
                        <dd className="mt-1 font-medium text-slate-900 dark:text-slate-100">{batch.total_count}</dd>
                    </div>
                    <div className="border-b border-slate-100 pb-2">
                        <dt className="text-slate-500">Completed</dt>
                        <dd className="mt-1 font-medium text-slate-900 dark:text-slate-100">{completedCount}</dd>
                    </div>
                    <div className="border-b border-slate-100 pb-2">
                        <dt className="text-slate-500">Failed</dt>
                        <dd className="mt-1 font-medium text-slate-900 dark:text-slate-100">{failedCount}</dd>
                    </div>
                    <div className="border-b border-slate-100 pb-2">
                        <dt className="text-slate-500">Pending</dt>
                        <dd className="mt-1 font-medium text-slate-900 dark:text-slate-100">{pendingCount}</dd>
                    </div>
                </dl>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <SegmentedControl
                        value={filter}
                        options={[
                            { value: 'all', label: 'All' },
                            { value: 'pending', label: 'Pending' },
                            { value: 'completed', label: 'Completed' },
                            { value: 'failed', label: 'Failed' },
                        ]}
                        onChange={setFilter}
                    />
                    {failedCount > 0 ? (
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            disabled={bulkRetrying}
                            onClick={retryAllFailed}
                            icon={<RotateCw className={`h-3 w-3 ${bulkRetrying ? 'animate-spin' : ''}`} />}
                        >
                            Retry All Failed
                        </Button>
                    ) : null}
                </div>

                <DataTable
                    busy={loading}
                    busyLabel="Loading batch items…"
                    columns={[
                        {
                            key: 'thumbnail',
                            label: '',
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
                            ) : (
                                <span className="font-mono text-xs">{item.flickr_photo_id}</span>
                            ),
                        },
                        {
                            key: 'status',
                            label: 'Status',
                            render: (item) => <StatusBadge status={item.status} />,
                        },
                        {
                            key: 'error',
                            label: 'Error',
                            render: (item) => item.error_message ? (
                                <span className="line-clamp-2 text-xs text-slate-600" title={item.error_message}>
                                    {item.error_message}
                                </span>
                            ) : '—',
                        },
                        {
                            key: 'actions',
                            label: '',
                            render: (item) => item.status === 'failed' ? (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    disabled={retryingId === item.flickr_photo_id}
                                    onClick={() => void retryIndividualItem(item.flickr_photo_id)}
                                >
                                    {retryingId === item.flickr_photo_id ? 'Retrying…' : 'Retry'}
                                </Button>
                            ) : null,
                        },
                    ]}
                    data={filteredItems}
                    rowKey={(item) => String(item.id)}
                    emptyMessage="No items found matching the selected filter."
                />
            </Modal.Body>
            <Modal.Footer>
                <Button type="button" variant="secondary" onClick={onClose}>
                    Close
                </Button>
            </Modal.Footer>

        </Modal>
            <PhotoDetailModal
                photo={selectedPhoto}
                accountPublicId={connectionId}
                onClose={() => setSelectedPhoto(null)}
            />
        </>
    );
}
