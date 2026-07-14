import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import BusyRegion from '@/Components/BusyRegion';
import Card from '@/Components/Card';
import ContactNsidLinks from '@/Components/ContactNsidLinks';
import CrawlActionBar from '@/Components/CrawlActionBar';
import FlickrPhotosetIdLinks from '@/Components/FlickrPhotosetIdLinks';
import { PageShell, PageShellCanvas, PageShellIdentity } from '@/Components/layout/page-shell';
import PhotoDetailModal from '@/Components/PhotoDetailModal';
import PhotoGrid from '@/Components/PhotoGrid';
import { useRemoteDataTable } from '@/hooks/useRemoteDataTable';
import AppLayout from '@/Layouts/AppLayout';
import { catalogPhotosetShowCrumbs } from '@/lib/breadcrumbs';
import { crawlSubjectForContact } from '@/lib/crawlSubject';
import { flickrCollectionThumbnailUrl } from '@/lib/flickrCollection';
import type { FlickrAccount, PageProps, Photo, Photoset } from '@/types';

interface Props extends PageProps {
    account: FlickrAccount | null;
    photoset: Photoset;
}

export default function CatalogPhotosetShow({ account, photoset }: Props) {
    const title = photoset.title?.trim() || 'Untitled';
    const coverUrl = flickrCollectionThumbnailUrl(photoset);
    const photoCount = photoset.photo_count ?? 0;
    const [selectedPhoto, setSelectedPhoto] = useState<Photo | null>(null);

    const filters = useMemo(() => ({ photoset_id: String(photoset.id) }), [photoset.id]);

    const { data: photos, loading, loadingMore, hasMore, loadMore, meta } = useRemoteDataTable<Photo>({
        fetchPath: '/api/v1/flickr/catalog/photos',
        filters,
        perPage: 48,
        paginationMode: 'append',
        initialSort: 'id',
        initialDirection: 'desc',
    });

    const loadedTotal = meta?.total ?? photoCount;

    const liveSelectedPhoto = useMemo(() => {
        if (selectedPhoto === null) {
            return null;
        }

        return photos.find((photo) => photo.id === selectedPhoto.id) ?? selectedPhoto;
    }, [photos, selectedPhoto]);

    return (
        <AppLayout>
            <Head title={title} />

            <PageShell>
                <PageShellIdentity
                    breadcrumbs={catalogPhotosetShowCrumbs(title, {
                        account,
                    })}
                    title={title}
                    subtitle={`${loadedTotal.toLocaleString()} photo${loadedTotal === 1 ? '' : 's'} in catalog`}
                    actions={
                        account?.public_id ? (
                            <CrawlActionBar
                                scope="contact"
                                accountPublicId={account.public_id}
                                contactNsid={photoset.owner_nsid}
                                subjectLabel={crawlSubjectForContact({
                                    nsid: photoset.owner_nsid,
                                    username: null,
                                    realname: photoset.title,
                                })}
                                showCrawl={false}
                                label="Crawl"
                            />
                        ) : undefined
                    }
                />

                <PageShellCanvas className="space-y-6" variant="plain">

                <Card title="Details">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                        {coverUrl ? (
                            <img
                                src={coverUrl}
                                alt={title}
                                className="h-24 w-24 shrink-0 rounded object-cover bg-slate-100"
                                loading="lazy"
                            />
                        ) : (
                            <div className="h-24 w-24 shrink-0 rounded bg-slate-100" aria-hidden />
                        )}

                        <dl className="grid min-w-0 flex-1 gap-3 text-sm sm:grid-cols-2">
                            <div className="flex justify-between gap-4 border-b border-slate-50 pb-2 sm:flex-col sm:justify-start sm:gap-1">
                                <dt className="text-slate-500">Owner</dt>
                                <dd>
                                    <ContactNsidLinks
                                        nsid={photoset.owner_nsid}
                                        accountPublicId={account?.public_id}
                                    />
                                </dd>
                            </div>
                            <div className="flex justify-between gap-4 border-b border-slate-50 pb-2 sm:flex-col sm:justify-start sm:gap-1">
                                <dt className="text-slate-500">Photoset ID</dt>
                                <dd>
                                    <FlickrPhotosetIdLinks
                                        photosetId={photoset.flickr_photoset_id}
                                        ownerNsid={photoset.owner_nsid}
                                        title={photoset.title}
                                        showSubtext={false}
                                    />
                                </dd>
                            </div>
                            <div className="flex justify-between gap-4 border-b border-slate-50 pb-2 sm:flex-col sm:justify-start sm:gap-1">
                                <dt className="text-slate-500">Photos (Flickr)</dt>
                                <dd className="font-medium text-slate-900">{photoCount > 0 ? photoCount.toLocaleString() : '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-4 border-b border-slate-50 pb-2 sm:flex-col sm:justify-start sm:gap-1">
                                <dt className="text-slate-500">Photos (catalog)</dt>
                                <dd className="font-medium text-slate-900">{loadedTotal.toLocaleString()}</dd>
                            </div>
                        </dl>
                    </div>
                </Card>

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Photos</h2>

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
                </section>
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
