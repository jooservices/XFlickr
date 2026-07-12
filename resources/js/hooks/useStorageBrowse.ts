import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { ApiError, apiGet, apiPost, apiDelete } from '@/lib/apiClient';
import {
    applyReauthorizationToAccounts,
    isStorageReauthorizationResponse,
} from '@/lib/storageApi';
import type {
    RemoteStorageAlbum,
    RemoteStorageItem,
    StorageAccount,
    StorageBrowseMeta,
} from '@/types';
import type {
    BrowseResponse,
    DeleteResponse,
    SyncResponse,
} from '@/types/storageBrowse';

export interface UseStorageBrowseOptions {
    provider: string;
    providerSlug: string;
    providerLabel: string;
}

export function useStorageBrowse({
    provider,
    providerSlug,
    providerLabel,
}: UseStorageBrowseOptions) {
    const [accounts, setAccounts] = useState<StorageAccount[]>([]);
    const [accountId, setAccountId] = useState<number | null>(null);
    const [containerId, setContainerId] = useState<string | null>(null);
    const [containerTitle, setContainerTitle] = useState<string | null>(null);
    const [albums, setAlbums] = useState<RemoteStorageAlbum[]>([]);
    const [items, setItems] = useState<RemoteStorageItem[]>([]);
    const [meta, setMeta] = useState<StorageBrowseMeta | null>(null);
    const [loading, setLoading] = useState(true);
    const [syncing, setSyncing] = useState(false);
    const [syncMode, setSyncMode] = useState<'background' | 'manual' | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [deleting, setDeleting] = useState(false);
    const syncInFlight = useRef(false);
    const syncDepthRef = useRef(0);

    const googlePhotosDeleteRequiresAlbum = provider === 'google_photos' && !containerId;

    const selectedAccount = useMemo(
        () => accounts.find((account) => account.id === accountId) ?? null,
        [accounts, accountId],
    );

    const loadAccounts = useCallback(async () => {
        const json = await apiGet<{ data: StorageAccount[] }>('/api/v1/storage/accounts', {
            params: { provider },
        });
        setAccounts(json.data);

        if (json.data.length > 0) {
            const defaultAccount = json.data.find((account) => account.is_default) ?? json.data[0];
            setAccountId(defaultAccount.id);
        } else {
            setAccountId(null);
        }
    }, [provider]);

    const loadBrowse = useCallback(
        async (options?: {
            albumPage?: number;
            itemPage?: number;
            appendAlbums?: boolean;
            appendItems?: boolean;
            nextContainerId?: string | null;
            silent?: boolean;
        }) => {
            if (accountId === null) {
                setAlbums([]);
                setItems([]);
                setMeta(null);
                setLoading(false);
                return;
            }

            const account = accounts.find((entry) => entry.id === accountId);
            if (account?.needs_reauthorization) {
                setAlbums([]);
                setItems([]);
                setMeta(null);
                setError(null);
                setLoading(false);
                return;
            }

            if (!options?.silent) {
                setLoading(true);
            }
            setError(null);

            const activeContainerId =
                options?.nextContainerId !== undefined ? options.nextContainerId : containerId;

            const albumPage = options?.albumPage ?? (options?.appendAlbums ? undefined : 1);
            const itemPage = options?.itemPage ?? (options?.appendItems ? undefined : 1);

            const params: Record<string, string | number> = {
                account_id: accountId,
                per_page: 25,
                source: 'local',
                album_page: options?.appendAlbums ? (meta?.album_page ?? 1) + 1 : (albumPage ?? 1),
                item_page: options?.appendItems ? (meta?.item_page ?? 1) + 1 : (itemPage ?? 1),
            };

            if (activeContainerId) {
                params.container_id = activeContainerId;
            }

            try {
                const json = await apiGet<BrowseResponse>(`/api/v1/storage/${providerSlug}/files`, { params });

                setAlbums((current) =>
                    options?.appendAlbums ? [...current, ...json.data.albums] : json.data.albums,
                );
                setItems((current) => (options?.appendItems ? [...current, ...json.data.items] : json.data.items));
                setMeta(json.meta ?? null);
            } catch (browseError) {
                if (browseError instanceof ApiError && isStorageReauthorizationResponse(browseError.status, browseError.body as BrowseResponse)) {
                    const payload = browseError.body as BrowseResponse;
                    setAccounts((current) =>
                        applyReauthorizationToAccounts(current, accountId, payload),
                    );
                    setAlbums([]);
                    setItems([]);
                    setMeta(null);
                    setError(null);
                    return;
                }

                setError(
                    browseError instanceof Error ? browseError.message : 'Failed to load storage content.',
                );
            } finally {
                if (!options?.silent) {
                    setLoading(false);
                }
            }
        },
        [accountId, accounts, containerId, meta, providerSlug],
    );

    const syncFromProvider = useCallback(
        async (options?: { manual?: boolean; maxBatches?: number }) => {
            if (accountId === null || syncInFlight.current) {
                return;
            }

            const account = accounts.find((entry) => entry.id === accountId);
            if (account?.needs_reauthorization) {
                return;
            }

            syncInFlight.current = true;
            syncDepthRef.current += 1;
            setSyncing(true);
            setSyncMode(options?.manual ? 'manual' : 'background');
            if (!options?.manual) {
                setError(null);
            }

            const params: Record<string, string | number> = {
                account_id: accountId,
            };
            if (containerId) {
                params.container_id = containerId;
            }

            try {
                const json = await apiPost<SyncResponse>(
                    `/api/v1/storage/${providerSlug}/sync-runs`,
                    {
                        max_batches: options?.maxBatches ?? (options?.manual ? 10 : 3),
                        reconcile: options?.manual === true,
                    },
                    { params },
                );

                await loadBrowse({
                    nextContainerId: containerId,
                    silent: true,
                });

                if (json.data.has_more && !options?.manual) {
                    await syncFromProvider({ maxBatches: 3 });
                }
            } catch (syncError) {
                if (syncError instanceof ApiError && isStorageReauthorizationResponse(syncError.status, syncError.body as SyncResponse)) {
                    const payload = syncError.body as SyncResponse;
                    setAccounts((current) =>
                        applyReauthorizationToAccounts(current, accountId, payload),
                    );
                    return;
                }

                if (options?.manual) {
                    setError(
                        syncError instanceof Error ? syncError.message : 'Failed to sync from provider.',
                    );
                } else {
                    const message =
                        syncError instanceof ApiError && typeof syncError.body === 'object' && syncError.body !== null && 'message' in syncError.body
                            ? String((syncError.body as SyncResponse).message)
                            : 'Background sync failed. Try Refresh from provider.';
                    setError(message);
                }
            } finally {
                syncDepthRef.current = Math.max(0, syncDepthRef.current - 1);
                syncInFlight.current = syncDepthRef.current > 0;
                if (syncDepthRef.current === 0) {
                    setSyncing(false);
                    setSyncMode(null);
                }
            }
        },
        [accountId, accounts, containerId, loadBrowse, providerSlug],
    );

    const deleteSelectedItems = useCallback(
        async (selectedKeys: string[], onDeleted: () => void) => {
            if (accountId === null || selectedKeys.length === 0) {
                return;
            }

            const account = accounts.find((entry) => entry.id === accountId);
            if (account?.needs_reauthorization) {
                return;
            }

            if (googlePhotosDeleteRequiresAlbum) {
                setError('Remove from album is only available inside an album (Google Photos API limitation).');
                return;
            }

            const count = selectedKeys.length;
            const isGooglePhotos = provider === 'google_photos';
            const confirmed = window.confirm(
                isGooglePhotos
                    ? `Remove ${count} item${count === 1 ? '' : 's'} from this album? The photo may still exist in your Google Photos library.`
                    : `Delete ${count} item${count === 1 ? '' : 's'} from ${providerLabel}? This cannot be undone.`,
            );

            if (!confirmed) {
                return;
            }

            setDeleting(true);
            setError(null);

            try {
                const json = await apiDelete<DeleteResponse>(`/api/v1/storage/${providerSlug}/files`, {
                    account_id: accountId,
                    item_ids: selectedKeys,
                    container_id: containerId ?? undefined,
                });

                const deletedIds = new Set(json.data.deleted);
                setItems((current) => current.filter((item) => !deletedIds.has(item.id)));
                onDeleted();

                if (json.data.failed.length > 0) {
                    const failedSummary = json.data.failed
                        .map((entry) => `${entry.id}: ${entry.message}`)
                        .join('; ');
                    setError(
                        json.data.deleted.length > 0
                            ? `Deleted ${json.data.deleted.length} item(s). Failed: ${failedSummary}`
                            : `Delete failed: ${failedSummary}`,
                    );
                }
            } catch (deleteError) {
                if (deleteError instanceof ApiError && isStorageReauthorizationResponse(deleteError.status, deleteError.body as DeleteResponse)) {
                    const payload = deleteError.body as DeleteResponse;
                    setAccounts((current) =>
                        applyReauthorizationToAccounts(current, accountId, payload),
                    );
                    return;
                }

                setError(deleteError instanceof Error ? deleteError.message : 'Failed to delete items.');
            } finally {
                setDeleting(false);
            }
        },
        [
            accountId,
            accounts,
            containerId,
            googlePhotosDeleteRequiresAlbum,
            provider,
            providerLabel,
            providerSlug,
        ],
    );

    useEffect(() => {
        void loadAccounts();
    }, [loadAccounts]);

    useEffect(() => {
        setContainerId(null);
        setContainerTitle(null);
    }, [accountId]);

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            await loadBrowse();
            if (!cancelled) {
                await syncFromProvider();
            }
        })();

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reload when account or container changes only
    }, [accountId, containerId, providerSlug]);

    const openContainer = useCallback(
        (album: RemoteStorageAlbum) => {
            setContainerId(album.id);
            setContainerTitle(album.title);
            setAlbums([]);
            setItems([]);
            void (async () => {
                await loadBrowse({ nextContainerId: album.id });
                await syncFromProvider();
            })();
        },
        [loadBrowse, syncFromProvider],
    );

    const clearContainer = useCallback(() => {
        setContainerId(null);
        setContainerTitle(null);
        setAlbums([]);
        setItems([]);
        void (async () => {
            await loadBrowse({ nextContainerId: null });
            await syncFromProvider();
        })();
    }, [loadBrowse, syncFromProvider]);

    return {
        accounts,
        accountId,
        setAccountId,
        containerId,
        containerTitle,
        albums,
        items,
        meta,
        loading,
        syncing,
        syncMode,
        error,
        deleting,
        googlePhotosDeleteRequiresAlbum,
        selectedAccount,
        loadBrowse,
        syncFromProvider,
        deleteSelectedItems,
        openContainer,
        clearContainer,
    };
}
