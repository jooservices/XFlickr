import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import CatalogOwnerNsidFilter from '@/Components/Catalog/OwnerNsidFilter';
import PhotoDetailModal from '@/Components/Catalog/PhotoDetailModal';
import ContactNsidLinks from '@/Components/Contacts/NsidLinks';
import CrawlActionBar from '@/Components/Flickr/CrawlActionBar';
import FlickrPhotoIdLinks from '@/Components/Flickr/PhotoIdLinks';
import { PageShell, PageShellCanvas, PageShellControlBar, PageShellIdentity } from '@/Components/Layout/page-shell';
import DataTable from '@/Components/ui/DataTable';
import Thumbnail from '@/Components/ui/Thumbnail';
import { useCatalogOwnerNsidTable } from '@/hooks/useCatalogOwnerNsidTable';
import AppLayout from '@/Layouts/AppLayout';
import { catalogPageCrumbs } from '@/lib/breadcrumbs';
import { crawlSubjectForContact, crawlSubjectForPhoto } from '@/lib/crawlSubject';
import { flickrPhotoGridUrl } from '@/lib/flickrPhoto';
import type { Favorite, FlickrAccount, PageProps, Photo } from '@/types';

interface Props extends PageProps {
    account: FlickrAccount | null;
}

export default function CatalogFavorites({ account }: Props) {
    const [selectedPhoto, setSelectedPhoto] = useState<Photo | null>(null);
    const { data: favorites, meta, setPage, loading, sortKey, sortDirection, handleSortChange, filterFormProps } =
        useCatalogOwnerNsidTable<Favorite>('subject_nsid', {
            fetchPath: '/api/v1/flickr/catalog/favorites',
            initialSort: 'id',
            initialDirection: 'desc',
        });

    const navigablePhotos = useMemo(
        () => favorites.flatMap((favorite) => (favorite.photo ? [favorite.photo] : [])),
        [favorites],
    );

    const liveSelectedPhoto = useMemo(() => {
        if (selectedPhoto === null) {
            return null;
        }

        const fromList = navigablePhotos.find((photo) => photo.id === selectedPhoto.id);

        return fromList ?? selectedPhoto;
    }, [navigablePhotos, selectedPhoto]);

    return (
        <AppLayout>
            <Head title="Favorites" />

            <PageShell>
                <PageShellIdentity
                    breadcrumbs={catalogPageCrumbs('Favorites', account)}
                    title="Favorites"
                    subtitle="Browse crawled favorites in the catalog."
                />

                <PageShellControlBar
                    filters={
                        <CatalogOwnerNsidFilter
                            {...filterFormProps}
                            placeholder="Filter by contact NSID"
                        />
                    }
                />

                <PageShellCanvas className="space-y-6" variant="plain">
                    <DataTable
                        busy={loading}
                        columns={[
                            {
                                key: 'thumbnail',
                                label: '',
                                render: (favorite) => {
                                    const photo = favorite.photo;

                                    return (
                                        <Thumbnail
                                            url={photo ? flickrPhotoGridUrl(photo) : null}
                                            alt={photo?.title || 'Photo'}
                                            size="md"
                                            onClick={photo ? () => setSelectedPhoto(photo) : undefined}
                                        />
                                    );
                                },
                            },
                            {
                                key: 'xflickr_photo_id',
                                label: 'Photo',
                                sortable: true,
                                render: (favorite) =>
                                    favorite.photo?.flickr_photo_id && favorite.photo.owner_nsid ? (
                                        <FlickrPhotoIdLinks
                                            photoId={favorite.photo.flickr_photo_id}
                                            ownerNsid={favorite.photo.owner_nsid}
                                            title={favorite.photo.title}
                                        />
                                    ) : (
                                        <span className="font-mono text-xs">{favorite.xflickr_photo_id}</span>
                                    ),
                            },
                            {
                                key: 'subject_nsid',
                                label: 'Contact',
                                sortable: true,
                                render: (favorite) => (
                                    <ContactNsidLinks
                                        nsid={favorite.subject_nsid}
                                        accountPublicId={account?.public_id}
                                    />
                                ),
                            },
                            {
                                key: 'photo_owner_nsid',
                                label: 'Owner',
                                sortable: true,
                                render: (favorite) => {
                                    const ownerNsid =
                                        favorite.photo_owner_nsid ?? favorite.photo?.owner_nsid;

                                    return ownerNsid ? (
                                        <ContactNsidLinks
                                            nsid={ownerNsid}
                                            accountPublicId={account?.public_id}
                                        />
                                    ) : (
                                        '—'
                                    );
                                },
                            },
                        ]}
                        data={favorites}
                        rowKey={(favorite) => String(favorite.id)}
                        sortKey={sortKey}
                        sortDirection={sortDirection}
                        onSortChange={handleSortChange}
                        emptyMessage="No favorites found."
                        actionsColumn={
                            account?.public_id
                                ? (favorite) =>
                                      favorite.photo?.flickr_photo_id ? (
                                          <CrawlActionBar
                                              scope="photo"
                                              accountPublicId={account.public_id}
                                              flickrPhotoId={favorite.photo.flickr_photo_id}
                                              subjectLabel={
                                                  favorite.photo
                                                      ? crawlSubjectForPhoto(favorite.photo)
                                                      : crawlSubjectForContact({
                                                            nsid: favorite.subject_nsid,
                                                            username: null,
                                                            realname: null,
                                                        })
                                              }
                                              showCrawl={false}
                                              label="Crawl"
                                          />
                                      ) : null
                                : undefined
                        }
                        actionsLabel="Crawl"
                        meta={meta ?? undefined}
                        onPageChange={setPage}
                    />
                </PageShellCanvas>
            </PageShell>

            <PhotoDetailModal
                photo={liveSelectedPhoto}
                photos={navigablePhotos}
                onSelectPhoto={setSelectedPhoto}
                accountPublicId={account?.public_id}
                onClose={() => setSelectedPhoto(null)}
            />
        </AppLayout>
    );
}
