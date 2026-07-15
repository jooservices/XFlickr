import { Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';

import CatalogOwnerNsidFilter from '@/Components/Catalog/OwnerNsidFilter';
import PhotoDetailModal from '@/Components/Catalog/PhotoDetailModal';
import PhotoDownloadedCell from '@/Components/Catalog/PhotoDownloadedCell';
import PhotoGrid from '@/Components/Catalog/PhotoGrid';
import PhotoMembershipLinks from '@/Components/Catalog/PhotoMembershipLinks';
import ContactNsidLinks from '@/Components/Contacts/NsidLinks';
import CrawlActionBar from '@/Components/Flickr/CrawlActionBar';
import { bulkDownloadActionIcon, bulkUploadActionIcon } from '@/Components/Flickr/CrawlTypeMenu';
import FlickrPhotoIdLinks from '@/Components/Flickr/PhotoIdLinks';
import { PageShell, PageShellCanvas, PageShellControlBar, PageShellIdentity } from '@/Components/Layout/page-shell';
import UploadConfirmModal, { type UploadConfirmPayload } from '@/Components/Transfer/UploadConfirmModal';
import type { BulkAction } from '@/Components/ui/BulkActionBar';
import BusyRegion from '@/Components/ui/BusyRegion';
import DataTable from '@/Components/ui/DataTable';
import EmptyState from '@/Components/ui/EmptyState';
import SegmentedControl from '@/Components/ui/SegmentedControl';
import Thumbnail from '@/Components/ui/Thumbnail';
import { useCatalogOwnerNsidTable } from '@/hooks/useCatalogOwnerNsidTable';
import { isLiveDownloadStatus, usePhotoDownloadProgress } from '@/hooks/usePhotoDownloadProgress';
import { useTableSelection } from '@/hooks/useTableSelection';
import AppLayout from '@/Layouts/AppLayout';
import { catalogPageCrumbs } from '@/lib/breadcrumbs';
import { crawlSubjectForPhoto } from '@/lib/crawlSubject';
import { flickrAccountPath } from '@/lib/flickrAccount';
import { flickrPhotoGridUrl } from '@/lib/flickrPhoto';
import type { FlickrAccount, PageProps, Photo } from '@/types';

interface Props extends PageProps {
    account: FlickrAccount | null;
}

function isPhotoSelectable(photo: Photo): boolean {
    return !isLiveDownloadStatus(photo.download_status ?? 'none');
}

export default function CatalogPhotos({ account }: Props) {
    const [viewMode, setViewMode] = useState<'table' | 'grid'>('table');
    const [selectedPhoto, setSelectedPhoto] = useState<Photo | null>(null);
    const [uploadConfirm, setUploadConfirm] = useState<{
        selectedKeys: string[];
        isMatching: boolean;
        count: number;
    } | null>(null);
    const {
        data: photos,
        meta,
        setPage,
        loading,
        loadingMore,
        hasMore,
        loadMore,
        reset,
        sortKey,
        sortDirection,
        handleSortChange,
        filterFormProps,
        appliedOwnerNsid,
        patchData,
    } = useCatalogOwnerNsidTable<Photo>('owner_nsid', {
        fetchPath: '/api/v1/flickr/catalog/photos',
        initialSort: 'id',
        initialDirection: 'desc',
        perPage: viewMode === 'grid' ? 48 : 25,
        paginationMode: viewMode === 'grid' ? 'append' : 'replace',
    });

    const { markPhotosPending } = usePhotoDownloadProgress(photos, patchData);

    const liveSelectedPhoto = useMemo(() => {
        if (selectedPhoto === null) {
            return null;
        }

        return photos.find((photo) => photo.id === selectedPhoto.id) ?? selectedPhoto;
    }, [photos, selectedPhoto]);

    const ownerFilter = appliedOwnerNsid.trim();
    const selectionClearKey = `${viewMode}|${sortKey}|${sortDirection}|${ownerFilter}`;

    const selection = useTableSelection({
        rowKey: (photo) => photo.flickr_photo_id,
        rows: photos,
        isRowSelectable: isPhotoSelectable,
        clearWhen: selectionClearKey,
        matchingTotal: meta?.total ?? null,
        allowSelectMatching: Boolean(account?.public_id) && ownerFilter !== '',
    });

    const postBulk = useCallback(
        (
            url: string,
            data: {
                flickr_photo_ids?: string[];
                select_all?: boolean;
                owner_nsid?: string;
                delete_local_after_upload?: boolean;
            },
            onQueued?: () => void,
        ) => {
            router.post(url, data, {
                preserveScroll: true,
                onSuccess: () => {
                    selection.clear();
                    onQueued?.();
                },
            });
        },
        [selection],
    );

    const bulkActions = useMemo<BulkAction<Photo>[]>(() => {
        if (!account?.public_id) {
            return [];
        }

        const accountPublicId = account.public_id;

        return [
            {
                id: 'download',
                label: 'Download',
                icon: bulkDownloadActionIcon(),
                onAction: ({ selectedKeys, isMatching }) => {
                    postBulk(
                        flickrAccountPath(accountPublicId, '/download'),
                        isMatching
                            ? { select_all: true, owner_nsid: ownerFilter }
                            : { flickr_photo_ids: selectedKeys },
                        () => markPhotosPending(isMatching ? 'visible' : selectedKeys),
                    );
                },
            },
            {
                id: 'upload',
                label: 'Upload',
                icon: bulkUploadActionIcon(),
                onAction: ({ selectedKeys, isMatching }) => {
                    setUploadConfirm({
                        selectedKeys,
                        isMatching,
                        count: isMatching ? (selection.matchingTotal ?? selectedKeys.length) : selectedKeys.length,
                    });
                },
            },
        ];
    }, [account?.public_id, markPhotosPending, ownerFilter, postBulk, selection.matchingTotal]);

    const switchViewMode = useCallback(
        (mode: 'table' | 'grid') => {
            if (mode === viewMode) {
                return;
            }

            reset();
            setViewMode(mode);
        },
        [reset, viewMode],
    );

    return (
        <AppLayout>
            <Head title="Photos" />

            <PageShell data-testid="catalog-photos-page">
                <PageShellIdentity
                    breadcrumbs={catalogPageCrumbs('Photos', account)}
                    title="Photos"
                    subtitle="Browse crawled photos in the catalog."
                />

                <PageShellControlBar
                    filters={<CatalogOwnerNsidFilter {...filterFormProps} />}
                    actions={
                        <SegmentedControl
                            value={viewMode}
                            options={[
                                { value: 'table', label: 'Table' },
                                { value: 'grid', label: 'Grid' },
                            ]}
                            onChange={switchViewMode}
                        />
                    }
                />

                <PageShellCanvas className="space-y-6" variant="plain">
                {viewMode === 'grid' ? (
                    <BusyRegion busy={loading} empty={photos.length === 0}>
                        <PhotoGrid
                            photos={photos}
                            accountPublicId={account?.public_id}
                            hasMore={hasMore}
                            loadingMore={loadingMore}
                            onLoadMore={loadMore}
                            onPhotoClick={setSelectedPhoto}
                            onDownloadQueued={(flickrPhotoId) => markPhotosPending([flickrPhotoId])}
                        />
                    </BusyRegion>
                ) : (
                    <DataTable
                        busy={loading}
                        columns={[
                            {
                                key: 'thumbnail',
                                label: '',
                                render: (photo) => (
                                    <Thumbnail
                                        url={flickrPhotoGridUrl(photo)}
                                        alt={photo.title || 'Photo'}
                                        size="md"
                                        onClick={() => setSelectedPhoto(photo)}
                                    />
                                ),
                            },
                            {
                                key: 'title',
                                label: 'Title',
                                sortable: true,
                                render: (photo) => photo.title || 'Untitled',
                            },
                            {
                                key: 'flickr_photo_id',
                                label: 'Photo ID',
                                sortable: true,
                                render: (photo) => (
                                    <FlickrPhotoIdLinks
                                        photoId={photo.flickr_photo_id}
                                        ownerNsid={photo.owner_nsid}
                                        title={photo.title}
                                    />
                                ),
                            },
                            {
                                key: 'photosets',
                                label: 'Photosets',
                                render: (photo) => (
                                    <PhotoMembershipLinks
                                        items={photo.photosets ?? []}
                                        kind="photoset"
                                        accountPublicId={account?.public_id}
                                    />
                                ),
                            },
                            {
                                key: 'galleries',
                                label: 'Galleries',
                                render: (photo) => (
                                    <PhotoMembershipLinks
                                        items={photo.galleries ?? []}
                                        kind="gallery"
                                        accountPublicId={account?.public_id}
                                    />
                                ),
                            },
                            {
                                key: 'owner_nsid',
                                label: 'Owner',
                                sortable: true,
                                render: (photo) => (
                                    <ContactNsidLinks nsid={photo.owner_nsid} accountPublicId={account?.public_id} />
                                ),
                            },
                            {
                                key: 'downloaded',
                                label: 'Downloaded',
                                render: (photo) => (
                                    <PhotoDownloadedCell
                                        status={photo.download_status}
                                        viewUrl={photo.stored_file_view_url}
                                    />
                                ),
                            },
                        ]}
                        data={photos}
                        rowKey={(photo) => photo.flickr_photo_id}
                        sortKey={sortKey}
                        sortDirection={sortDirection}
                        onSortChange={handleSortChange}
                        emptyMessage={
                            <EmptyState
                                title="No photos found."
                                description={
                                    ownerFilter
                                        ? `No photos match owner NSID “${ownerFilter}”.`
                                        : 'Crawl photos for a contact or clear filters to see the catalog.'
                                }
                            />
                        }
                        selection={account?.public_id ? selection.tableSelection : undefined}
                        bulkActions={account?.public_id ? bulkActions : undefined}
                        onBulkClear={account?.public_id ? selection.clear : undefined}
                        matchingLabel="photos"
                        actionsColumn={
                            account?.public_id
                                ? (photo) => (
                                      <CrawlActionBar
                                          scope="photo"
                                          accountPublicId={account.public_id}
                                          flickrPhotoId={photo.flickr_photo_id}
                                          subjectLabel={crawlSubjectForPhoto(photo)}
                                          showCrawl={false}
                                          label="Actions"
                                          onDownloadQueued={() => markPhotosPending([photo.flickr_photo_id])}
                                      />
                                  )
                                : undefined
                        }
                        actionsLabel="Actions"
                        meta={meta ?? undefined}
                        onPageChange={setPage}
                    />
                )}
                </PageShellCanvas>
            </PageShell>

            <PhotoDetailModal
                photo={liveSelectedPhoto}
                photos={photos}
                onSelectPhoto={setSelectedPhoto}
                accountPublicId={account?.public_id}
                onClose={() => setSelectedPhoto(null)}
                onDownloadQueued={(flickrPhotoId) => markPhotosPending([flickrPhotoId])}
            />

            {uploadConfirm && account?.public_id ? (
                <UploadConfirmModal
                    open
                    onClose={() => setUploadConfirm(null)}
                    onConfirm={(payload: UploadConfirmPayload) => {
                        const accountPublicId = account.public_id;
                        postBulk(
                            flickrAccountPath(accountPublicId, '/upload'),
                            uploadConfirm.isMatching
                                ? { select_all: true, owner_nsid: ownerFilter, delete_local_after_upload: payload.deleteLocalAfterUpload }
                                : { flickr_photo_ids: uploadConfirm.selectedKeys, delete_local_after_upload: payload.deleteLocalAfterUpload },
                        );
                        setUploadConfirm(null);
                    }}
                    selectedCount={uploadConfirm.count}
                    isMatching={uploadConfirm.isMatching}
                />
            ) : null}
        </AppLayout>
    );
}
