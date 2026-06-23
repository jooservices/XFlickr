import type { FlickrAccount, TransferBatch } from '@/types';

export function accountLabel(account: FlickrAccount | undefined): string {
    if (!account) {
        return '—';
    }

    return account.fullname || account.username || account.nsid;
}

export function downloadGroupLabel(batch: TransferBatch): string {
    if (batch.group_type === 'photoset') {
        return `Photoset · ${batch.group_label ?? batch.group_id ?? '—'}`;
    }

    if (batch.group_type === 'gallery') {
        return `Gallery · ${batch.group_label ?? batch.group_id ?? '—'}`;
    }

    if (batch.group_type === 'owner') {
        return 'Loose photos';
    }

    return batch.subject_nsid ?? 'All photos';
}

export function downloadStoragePath(batch: TransferBatch): string {
    const ownerNsid = batch.subject_nsid ?? 'unknown';

    return `storage/app/private/flickr/${ownerNsid}/photos/`;
}

export function fetchRunSortValue(
    run: { id: number; connection_key: string; crawl_type: string; subject_nsid: string | null; photos_discovered: number; contacts_discovered: number; status: string },
    key: string,
    accountByNsid: Record<string, FlickrAccount>,
): string | number {
    switch (key) {
        case 'id':
            return run.id;
        case 'account':
            return accountLabel(accountByNsid[run.connection_key]);
        case 'crawl_type':
            return run.crawl_type;
        case 'subject':
            return run.subject_nsid ?? run.connection_key;
        case 'progress':
            return run.photos_discovered + run.contacts_discovered;
        case 'status':
            return run.status;
        default:
            return run.id;
    }
}

export function transferBatchSortValue(
    batch: TransferBatch,
    key: string,
    accountByNsid: Record<string, FlickrAccount>,
    groupLabel: (batch: TransferBatch) => string,
): string | number {
    switch (key) {
        case 'id':
            return batch.id;
        case 'account':
            return accountLabel(accountByNsid[batch.connection_key]);
        case 'owner':
            return batch.subject_nsid ?? '';
        case 'group':
            return groupLabel(batch);
        case 'storage':
            return downloadStoragePath(batch);
        case 'subject':
            return batch.subject_nsid ?? 'All photos';
        case 'progress':
            return batch.completed_count + batch.failed_count;
        case 'status':
            return batch.status;
        default:
            return batch.id;
    }
}
