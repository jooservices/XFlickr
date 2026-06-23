import { Head } from '@inertiajs/react';

import Breadcrumbs from '@/Components/Breadcrumbs';
import CatalogOwnerNsidFilter from '@/Components/CatalogOwnerNsidFilter';
import ContactNsidLinks from '@/Components/ContactNsidLinks';
import CrawlActionBar from '@/Components/CrawlActionBar';
import DataTable from '@/Components/DataTable';
import FlickrPhotoIdLinks from '@/Components/FlickrPhotoIdLinks';
import PageHeading from '@/Components/PageHeading';
import PhotoMembershipLinks from '@/Components/PhotoMembershipLinks';
import Thumbnail from '@/Components/Thumbnail';
import { useCatalogOwnerNsidTable } from '@/hooks/useCatalogOwnerNsidTable';
import AppLayout from '@/Layouts/AppLayout';
import { catalogPageCrumbs } from '@/lib/breadcrumbs';
import { flickrPhotoThumbnailUrl } from '@/lib/flickrPhoto';
import type { FlickrAccount, PageProps, Photo } from '@/types';

interface Props extends PageProps {
    account: FlickrAccount | null;
}

export default function CatalogPhotos({ account }: Props) {
    const { data: photos, meta, setPage, loading, sortKey, sortDirection, handleSortChange, filterFormProps } =
        useCatalogOwnerNsidTable<Photo>('owner_nsid', {
            fetchPath: '/api/flickr/catalog/photos',
            initialSort: 'id',
            initialDirection: 'desc',
        });

    return (
        <AppLayout>
            <Head title="Photos" />

            <div className="space-y-6">
                <PageHeading
                    breadcrumbs={<Breadcrumbs items={catalogPageCrumbs('Photos', account)} />}
                    title="Photos"
                    subtitle="Browse crawled photos in the catalog."
                />

                <CatalogOwnerNsidFilter {...filterFormProps} />

                {loading ? (
                    <p className="text-sm text-slate-500">Loading…</p>
                ) : (
                    <DataTable
                        columns={[
                            {
                                key: 'thumbnail',
                                label: '',
                                render: (photo) => (
                                    <Thumbnail
                                        url={flickrPhotoThumbnailUrl(photo)}
                                        alt={photo.title || 'Photo'}
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
                                    <ContactNsidLinks
                                        nsid={photo.owner_nsid}
                                        accountPublicId={account?.public_id}
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
                                          showCrawl={false}
                                          label="Crawl"
                                      />
                                  )
                                : undefined
                        }
                        actionsLabel="Crawl"
                        meta={meta ?? undefined}
                        onPageChange={setPage}
                    />
                )}
            </div>
        </AppLayout>
    );
}
