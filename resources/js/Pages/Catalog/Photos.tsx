import { Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';

import type { BulkAction } from '@/Components/BulkActionBar';
import BusyRegion from '@/Components/BusyRegion';
import Button from '@/Components/Button';
import CatalogOwnerNsidFilter from '@/Components/CatalogOwnerNsidFilter';
import ContactNsidLinks from '@/Components/ContactNsidLinks';
import CrawlActionBar from '@/Components/CrawlActionBar';
import { bulkDownloadActionIcon, bulkUploadActionIcon } from '@/Components/CrawlTypeMenu';
import DataTable from '@/Components/DataTable';
import FlickrPhotoIdLinks from '@/Components/FlickrPhotoIdLinks';
import { PageShell, PageShellCanvas, PageShellControlBar, PageShellIdentity } from '@/Components/layout/page-shell';
import PhotoDetailModal from '@/Components/PhotoDetailModal';
import PhotoDownloadedCell from '@/Components/PhotoDownloadedCell';
import PhotoGrid from '@/Components/PhotoGrid';
import PhotoMembershipLinks from '@/Components/PhotoMembershipLinks';
import Thumbnail from '@/Components/Thumbnail';
import { useCatalogOwnerNsidTable } from '@/hooks/useCatalogOwnerNsidTable';
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
    return (photo.download_status ?? 'none') !== 'downloading';
}

export default function CatalogPhotos({ account }: Props) {
    const [viewMode, setViewMode] = useState<'table' | 'grid'>('table');
    const [selectedPhoto, setSelectedPhoto] = useState<Photo | null>(null);
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
    } = useCatalogOwnerNsidTable<Photo>('owner_nsid', {
        fetchPath: '/api/v1/flickr/catalog/photos',
        initialSort: 'id',
        initialDirection: 'desc',
        perPage: viewMode === 'grid' ? 48 : 25,
        paginationMode: viewMode === 'grid' ? 'append' : 'replace',
    });

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
            },
        ) => {
            router.post(url, data, {
                preserveScroll: true,
                onSuccess: () => selection.clear(),
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
                    );
                },
            },
            {
                id: 'upload',
                label: 'Upload',
                icon: bulkUploadActionIcon(),
                onAction: ({ selectedKeys, isMatching }) => {
                    postBulk(
                        flickrAccountPath(accountPublicId, '/upload'),
                        isMatching
                            ? { select_all: true, owner_nsid: ownerFilter }
                            : { flickr_photo_ids: selectedKeys },
                    );
                },
            },
        ];
    }, [account?.public_id, ownerFilter, postBulk]);

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
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant={viewMode === 'table' ? 'primary' : 'secondary'}
                                onClick={() => switchViewMode('table')}
                            >
                                Table
                            </Button>
                            <Button
                                type="button"
                                variant={viewMode === 'grid' ? 'primary' : 'secondary'}
                                onClick={() => switchViewMode('grid')}
                            >
                                Grid
                            </Button>
                        </div>
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
                        emptyMessage="No photos found."
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
                accountPublicId={account?.public_id}
                onClose={() => setSelectedPhoto(null)}
            />
        </AppLayout>
    );
}
