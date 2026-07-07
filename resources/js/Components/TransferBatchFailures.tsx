import { useCallback, useState } from 'react';

import Button from '@/Components/Button';
import { apiGet, apiPost } from '@/lib/apiClient';
import { flickrApiAccountPath } from '@/lib/flickrAccount';
import type { FlickrAccount, TransferBatch, TransferItem } from '@/types';

interface BatchDetailResponse {
    batch: TransferBatch;
    items: TransferItem[];
}

interface TransferBatchFailuresProps {
    batch: TransferBatch;
    account: FlickrAccount | undefined;
}

export default function TransferBatchFailures({ batch, account }: TransferBatchFailuresProps) {
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<TransferItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [retryingId, setRetryingId] = useState<string | null>(null);

    const loadFailures = useCallback(async () => {
        if (!account) {
            return;
        }

        setLoading(true);

        try {
            const response = await apiGet<BatchDetailResponse>(
                flickrApiAccountPath(account.public_id, `/transfers/${batch.id}`),
            );
            const failed = response.items.filter((item) => item.status === 'failed');
            setItems(failed);
        } finally {
            setLoading(false);
        }
    }, [account, batch.id]);

    const toggle = () => {
        const next = !open;
        setOpen(next);

        if (next && items.length === 0) {
            void loadFailures();
        }
    };

    const retryItem = async (flickrPhotoId: string) => {
        if (!account) {
            return;
        }

        setRetryingId(flickrPhotoId);

        try {
            await apiPost(
                flickrApiAccountPath(account.public_id, `/transfers/${batch.id}/items/${flickrPhotoId}/retry`),
            );
            setItems((current) => current.filter((item) => item.flickr_photo_id !== flickrPhotoId));
        } finally {
            setRetryingId(null);
        }
    };

    if (batch.failed_count <= 0) {
        return null;
    }

    return (
        <div className="mt-2">
            <Button type="button" variant="ghost" onClick={toggle}>
                {open ? 'Hide' : 'View'} failed items ({batch.failed_count})
            </Button>
            {open ? (
                <ul className="mt-2 space-y-2 rounded border border-slate-200 bg-slate-50 p-2 dark:border-slate-700 dark:bg-slate-900/50">
                    {loading ? <li className="text-xs text-slate-500">Loading…</li> : null}
                    {!loading && items.length === 0 ? (
                        <li className="text-xs text-slate-500">No failed items in this batch.</li>
                    ) : null}
                    {items.map((item) => (
                        <li key={item.id} className="flex items-start justify-between gap-2 text-xs">
                            <div className="min-w-0">
                                <p className="font-mono text-slate-700 dark:text-slate-200">{item.flickr_photo_id}</p>
                                {item.error_message ? (
                                    <p className="line-clamp-2 text-red-600" title={item.error_message}>
                                        {item.error_message}
                                    </p>
                                ) : null}
                            </div>
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={retryingId === item.flickr_photo_id}
                                onClick={() => void retryItem(item.flickr_photo_id)}
                            >
                                Retry
                            </Button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
