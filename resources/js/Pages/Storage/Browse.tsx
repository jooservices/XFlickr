import { Head } from '@inertiajs/react';
import { useMemo } from 'react';

import { PageShell, PageShellCanvas, PageShellControlBar, PageShellIdentity } from '@/Components/layout/page-shell';
import StorageReauthorizeBanner from '@/Components/Storage/StorageReauthorizeBanner';
import type { BulkAction } from '@/Components/ui/BulkActionBar';
import DataTable from '@/Components/ui/DataTable';
import LoadingIndicator from '@/Components/ui/LoadingIndicator';
import Thumbnail from '@/Components/ui/Thumbnail';
import { useStorageBrowse } from '@/hooks/useStorageBrowse';
import { useTableSelection } from '@/hooks/useTableSelection';
import { useTableSort } from '@/hooks/useTableSort';
import AppLayout from '@/Layouts/AppLayout';
import { storageBrowseCrumbs } from '@/lib/breadcrumbs';
import { formatBytes, formatSyncedAt } from '@/lib/format';
import { sortClientData } from '@/lib/tableSort';
import type { PageProps, RemoteStorageAlbum, RemoteStorageItem } from '@/types';

interface Props extends PageProps {
    provider: string;
    provider_slug: string;
    provider_label: string;
    container_label: string;
}

export default function StorageBrowse({
    provider,
    provider_slug,
    provider_label,
    container_label,
}: Props) {
    const {
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
    } = useStorageBrowse({
        provider,
        providerSlug: provider_slug,
        providerLabel: provider_label,
    });

    const albumSort = useTableSort({ initialSort: 'title', initialDirection: 'asc' });
    const itemSort = useTableSort({ initialSort: 'name', initialDirection: 'asc' });

    const sortedAlbums = useMemo(
        () =>
            sortClientData(albums, albumSort.sortKey, albumSort.sortDirection, (album, key) => {
                if (key === 'title') {
                    return album.title;
                }

                if (key === 'media_items_count') {
                    return album.media_items_count ?? -1;
                }

                return album.title;
            }),
        [albums, albumSort.sortKey, albumSort.sortDirection],
    );

    const sortedItems = useMemo(
        () =>
            sortClientData(items, itemSort.sortKey, itemSort.sortDirection, (item, key) => {
                switch (key) {
                    case 'name':
                        return item.name;
                    case 'mime_type':
                        return item.mime_type ?? '';
                    case 'size':
                        return item.size ?? -1;
                    case 'modified_at':
                        return item.modified_at ? new Date(item.modified_at).getTime() : -1;
                    default:
                        return item.name;
                }
            }),
        [items, itemSort.sortKey, itemSort.sortDirection],
    );

    const itemSelectionClearKey = `${accountId ?? ''}|${containerId ?? ''}|${sortedItems.map((item) => item.id).join(',')}`;

    const itemSelection = useTableSelection({
        rowKey: (item) => item.id,
        rows: sortedItems,
        clearWhen: itemSelectionClearKey,
    });

    const returnUrl = `/storages/${provider_slug}`;
    const lastSyncedLabel = formatSyncedAt(meta?.last_synced_at);

    const itemBulkActions = useMemo<BulkAction<RemoteStorageItem>[]>(
        () => [
            {
                id: 'delete',
                label: provider === 'google_photos' ? 'Remove from album' : 'Delete',
                variant: 'destructive',
                disabled: () =>
                    deleting ||
                    loading ||
                    syncing ||
                    accountId === null ||
                    selectedAccount?.needs_reauthorization === true ||
                    googlePhotosDeleteRequiresAlbum,
                onAction: ({ selectedKeys }) => {
                    void deleteSelectedItems(selectedKeys, () => itemSelection.clear());
                },
            },
        ],
        [
            accountId,
            deleteSelectedItems,
            deleting,
            googlePhotosDeleteRequiresAlbum,
            itemSelection,
            loading,
            provider,
            selectedAccount?.needs_reauthorization,
            syncing,
        ],
    );

    return (
        <AppLayout>
            <Head title={provider_label} />

            <PageShell data-testid="storage-browse-page">
                <PageShellIdentity
                    breadcrumbs={storageBrowseCrumbs(provider_label)}
                    title={provider_label}
                    subtitle="Cached locally for fast browsing. Syncs from the provider in the background."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {lastSyncedLabel ? (
                                <span className="text-xs text-slate-500">Last synced {lastSyncedLabel}</span>
                            ) : null}
                            <button
                                type="button"
                                disabled={
                                    syncing ||
                                    loading ||
                                    accountId === null ||
                                    selectedAccount?.needs_reauthorization === true
                                }
                                onClick={() => void syncFromProvider({ manual: true, maxBatches: 10 })}
                                className="rounded-md border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-50"
                            >
                                {syncing ? 'Refreshing…' : 'Refresh from provider'}
                            </button>
                        </div>
                    }
                />

                <PageShellControlBar
                    filters={
                        <div className="flex flex-wrap items-center gap-3">
                    <label className="text-sm text-slate-600" htmlFor="storage-account">
                        Account
                    </label>
                    <select
                        id="storage-account"
                        value={accountId ?? ''}
                        onChange={(event) => setAccountId(Number(event.target.value) || null)}
                        className="rounded-md border border-slate-200 px-3 py-2 text-sm"
                        disabled={accounts.length === 0}
                    >
                        {accounts.length === 0 ? (
                            <option value="">No connected account</option>
                        ) : (
                            accounts.map((account) => (
                                <option key={account.id} value={account.id}>
                                    {account.label}
                                    {account.is_default ? ' (default)' : ''}
                                    {account.needs_reauthorization ? ' — needs reauthorization' : ''}
                                </option>
                            ))
                        )}
                    </select>

                    {accounts.length === 0 ? (
                        <a
                            href="/connections?provider=storage"
                            className="text-sm font-medium text-cyan-700 hover:text-cyan-800"
                        >
                            Connect in Settings
                        </a>
                    ) : null}

                        </div>
                    }
                />

                <PageShellCanvas className="space-y-6" variant="plain">
                                {containerId && containerTitle ? (
                    <div className="flex items-center gap-2 text-sm text-slate-600">
                        <button
                            type="button"
                            onClick={clearContainer}
                            className="rounded-md border border-slate-200 px-2 py-1 hover:bg-slate-50"
                        >
                            All
                        </button>
                        <span>/</span>
                        <span className="font-medium text-slate-900">{containerTitle}</span>
                    </div>
                ) : null}

                {selectedAccount?.needs_reauthorization ? (
                    <StorageReauthorizeBanner account={selectedAccount} returnUrl={returnUrl} />
                ) : null}

                {syncing && !selectedAccount?.needs_reauthorization ? (
                    <div className="flex items-center gap-3 rounded-md border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">
                        <LoadingIndicator
                            size="sm"
                            className="text-cyan-700 [&_svg]:text-cyan-700"
                            label={
                                syncMode === 'manual'
                                    ? `Refreshing from ${provider_label}…`
                                    : `Syncing from ${provider_label} in the background… Photos will appear as they are cached.`
                            }
                        />
                    </div>
                ) : null}

                {error ? (
                    <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {error}
                    </div>
                ) : null}

                {!selectedAccount?.needs_reauthorization && !containerId ? (
                    <div className="space-y-3">
                        <h2 className="text-lg font-medium text-slate-900">{container_label}s</h2>
                        <DataTable
                            busy={loading}
                            columns={[
                                    {
                                        key: 'cover',
                                        label: 'Cover',
                                        render: (album: RemoteStorageAlbum) => (
                                            <Thumbnail url={album.cover_thumbnail_url} alt={album.title} />
                                        ),
                                    },
                                    {
                                        key: 'title',
                                        label: 'Title',
                                        sortable: true,
                                        render: (album: RemoteStorageAlbum) => (
                                            <button
                                                type="button"
                                                onClick={() => openContainer(album)}
                                                className="font-medium text-cyan-700 hover:text-cyan-800"
                                            >
                                                {album.title}
                                            </button>
                                        ),
                                    },
                                    {
                                        key: 'media_items_count',
                                        label: 'Items',
                                        sortable: true,
                                        render: (album: RemoteStorageAlbum) => album.media_items_count ?? '—',
                                    },
                                ]}
                                data={sortedAlbums}
                                rowKey={(album) => album.id}
                                sortKey={albumSort.sortKey}
                                sortDirection={albumSort.sortDirection}
                                onSortChange={albumSort.handleSortChange}
                                emptyMessage={
                                    syncing
                                        ? `Syncing ${container_label.toLowerCase()}s from provider…`
                                        : `No ${container_label.toLowerCase()}s found.`
                                }
                            />

                        {meta?.has_more_albums ? (
                            <button
                                type="button"
                                disabled={loading}
                                onClick={() => void loadBrowse({ appendAlbums: true })}
                                className="rounded-md border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-50"
                            >
                                Load more {container_label.toLowerCase()}s
                            </button>
                        ) : null}
                    </div>
                ) : null}

                {!selectedAccount?.needs_reauthorization ? (
                    <div className="space-y-3">
                        <h2 className="text-lg font-medium text-slate-900">Photos</h2>
                        {googlePhotosDeleteRequiresAlbum ? (
                            <p className="text-sm text-slate-500">
                                Remove from album is only available inside an album (Google Photos API limitation).
                            </p>
                        ) : null}
                        <DataTable
                            busy={loading}
                            columns={[
                                    {
                                        key: 'thumbnail',
                                        label: '',
                                        render: (item: RemoteStorageItem) => (
                                            <Thumbnail
                                                url={item.thumbnail_url}
                                                alt={item.name}
                                                href={item.web_url}
                                            />
                                        ),
                                    },
                                    {
                                        key: 'name',
                                        label: 'Name',
                                        sortable: true,
                                        render: (item: RemoteStorageItem) => item.name,
                                    },
                                    {
                                        key: 'mime_type',
                                        label: 'Type',
                                        sortable: true,
                                        render: (item: RemoteStorageItem) => item.mime_type ?? '—',
                                    },
                                    {
                                        key: 'size',
                                        label: 'Size',
                                        sortable: true,
                                        render: (item: RemoteStorageItem) => formatBytes(item.size),
                                    },
                                    {
                                        key: 'modified_at',
                                        label: 'Modified',
                                        sortable: true,
                                        render: (item: RemoteStorageItem) =>
                                            item.modified_at ? new Date(item.modified_at).toLocaleString() : '—',
                                    },
                                ]}
                                data={sortedItems}
                                rowKey={(item) => item.id}
                                sortKey={itemSort.sortKey}
                                sortDirection={itemSort.sortDirection}
                                onSortChange={itemSort.handleSortChange}
                                emptyMessage={
                                    syncing ? 'Syncing photos from provider…' : 'No photos found.'
                                }
                                selection={itemSelection.tableSelection}
                                bulkActions={itemBulkActions}
                                onBulkClear={itemSelection.clear}
                                actionsColumn={
                                    provider_slug === 'r2' && accountId
                                        ? (item: RemoteStorageItem) => (
                                              <a
                                                  href={`/api/v1/storage/r2/files/download?account_id=${accountId}&path=${encodeURIComponent(item.path ?? item.id)}`}
                                                  className="text-sm font-medium text-cyan-700 hover:text-cyan-800"
                                              >
                                                  Download
                                              </a>
                                          )
                                        : undefined
                                }
                                actionsLabel="Download"
                            />

                        {meta?.has_more_items ? (
                            <button
                                type="button"
                                disabled={loading}
                                onClick={() => void loadBrowse({ appendItems: true })}
                                className="rounded-md border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-50"
                            >
                                Load more photos
                            </button>
                        ) : null}
                    </div>
                ) : null}
                </PageShellCanvas>
            </PageShell>
        </AppLayout>
    );
}
