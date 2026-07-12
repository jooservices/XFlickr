import type {
    RemoteStorageAlbum,
    RemoteStorageItem,
    StorageAccount,
    StorageBrowseMeta,
} from '@/types';

export interface BrowseResponse {
    data: {
        albums: RemoteStorageAlbum[];
        items: RemoteStorageItem[];
    };
    meta: StorageBrowseMeta;
    needs_reauthorization?: boolean;
    reauthorize_url?: string;
    missing_scopes?: StorageAccount['missing_scopes'];
    message?: string;
}

export interface SyncResponse {
    data: {
        albums_synced: number;
        items_synced: number;
        has_more: boolean;
        last_synced_at: string | null;
        albums_complete: boolean;
        items_complete: boolean;
    };
    needs_reauthorization?: boolean;
    reauthorize_url?: string;
    missing_scopes?: StorageAccount['missing_scopes'];
    message?: string;
}

export interface DeleteResponse {
    data: {
        deleted: string[];
        failed: Array<{ id: string; message: string }>;
    };
    needs_reauthorization?: boolean;
    reauthorize_url?: string;
    missing_scopes?: StorageAccount['missing_scopes'];
    message?: string;
}
