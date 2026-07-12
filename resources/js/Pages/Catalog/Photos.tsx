import { Head } from '@inertiajs/react';
import { useCallback, useState } from 'react';

import Button from '@/Components/Button';
import CatalogOwnerNsidFilter from '@/Components/CatalogOwnerNsidFilter';
import ContactNsidLinks from '@/Components/ContactNsidLinks';
import CrawlActionBar from '@/Components/CrawlActionBar';
import DataTable from '@/Components/DataTable';
import FlickrPhotoIdLinks from '@/Components/FlickrPhotoIdLinks';
import { PageShell, PageShellCanvas, PageShellControlBar, PageShellIdentity } from '@/Components/layout/page-shell';
import PhotoDownloadedCell from '@/Components/PhotoDownloadedCell';
import PhotoGrid from '@/Components/PhotoGrid';
import PhotoMembershipLinks from '@/Components/PhotoMembershipLinks';
import Thumbnail from '@/Components/Thumbnail';
import { useCatalogOwnerNsidTable } from '@/hooks/useCatalogOwnerNsidTable';
import AppLayout from '@/Layouts/AppLayout';
import { catalogPageCrumbs } from '@/lib/breadcrumbs';
import { crawlSubjectForPhoto } from '@/lib/crawlSubject';
import { flickrPhotoThumbnailUrl } from '@/lib/flickrPhoto';
import type { FlickrAccount, PageProps, Photo } from '@/types';

interface Props extends PageProps {
    account: FlickrAccount | null;
}

export default function CatalogPhotos({ account }: Props) {
    const [viewMode, setViewMode] = useState<'table' | 'grid'>('table');
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
    } = useCatalogOwnerNsidTable<Photo>('owner_nsid', {
        fetchPath: '/api/v1/flickr/catalog/photos',
        initialSort: 'id',
        initialDirection: 'desc',
        perPage: viewMode === 'grid' ? 48 : 25,
        paginationMode: viewMode === 'grid' ? 'append' : 'replace',
    });

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

    const showInitialLoading = loading && photos.length === 0;

    return (
        <AppLayout>
            <Head title="Photos" />

            <PageShell>
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
                {showInitialLoading ? (
                    <p className="text-sm text-slate-500">Loading…</p>
                ) : viewMode === 'grid' ? (
                    <PhotoGrid
                        photos={photos}
                        accountPublicId={account?.public_id}
                        hasMore={hasMore}
                        loadingMore={loadingMore}
                        onLoadMore={loadMore}
                    />
                ) : (
                    <DataTable
                        columns={[
                            {
                                key: 'thumbnail',
                                label: '',
                                render: (photo) => (
                                    <Thumbnail url={flickrPhotoThumbnailUrl(photo)} alt={photo.title || 'Photo'} />
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
                        rowKey={(photo) => String(photo.id)}
                        sortKey={sortKey}
                        sortDirection={sortDirection}
                        onSortChange={handleSortChange}
                        emptyMessage="No photos found."
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
        </AppLayout>
    );
}
