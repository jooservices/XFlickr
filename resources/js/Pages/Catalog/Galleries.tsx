import { Head } from '@inertiajs/react';

import CatalogOwnerNsidFilter from '@/Components/CatalogOwnerNsidFilter';
import ContactNsidLinks from '@/Components/ContactNsidLinks';
import CrawlActionBar from '@/Components/CrawlActionBar';
import DataTable from '@/Components/DataTable';
import FlickrGalleryIdLinks from '@/Components/FlickrGalleryIdLinks';
import { PageShell, PageShellCanvas, PageShellControlBar, PageShellIdentity } from '@/Components/layout/page-shell';
import Thumbnail from '@/Components/Thumbnail';
import { useCatalogOwnerNsidTable } from '@/hooks/useCatalogOwnerNsidTable';
import AppLayout from '@/Layouts/AppLayout';
import { catalogPageCrumbs } from '@/lib/breadcrumbs';
import { crawlSubjectForContact } from '@/lib/crawlSubject';
import { flickrCollectionThumbnailUrl } from '@/lib/flickrCollection';
import type { FlickrAccount, Gallery, PageProps } from '@/types';

interface Props extends PageProps {
    account: FlickrAccount | null;
}

export default function CatalogGalleries({ account }: Props) {
    const { data: galleries, meta, setPage, loading, sortKey, sortDirection, handleSortChange, filterFormProps } =
        useCatalogOwnerNsidTable<Gallery>('owner_nsid', {
            fetchPath: '/api/v1/flickr/catalog/galleries',
            initialSort: 'id',
            initialDirection: 'desc',
        });

    return (
        <AppLayout>
            <Head title="Galleries" />

            <PageShell>
                <PageShellIdentity
                    breadcrumbs={catalogPageCrumbs('Galleries', account)}
                    title="Galleries"
                    subtitle="Browse crawled galleries in the catalog."
                />

                <PageShellControlBar filters={<CatalogOwnerNsidFilter {...filterFormProps} />} />

                <PageShellCanvas className="space-y-6" variant="plain">
                {loading ? (
                    <p className="text-sm text-slate-500">Loading…</p>
                ) : (
                    <DataTable
                        columns={[
                            {
                                key: 'thumbnail',
                                label: '',
                                render: (gallery) => (
                                    <Thumbnail
                                        url={flickrCollectionThumbnailUrl(gallery)}
                                        alt={gallery.title || 'Gallery'}
                                    />
                                ),
                            },
                            {
                                key: 'title',
                                label: 'Title',
                                sortable: true,
                                render: (gallery) => gallery.title || 'Untitled',
                            },
                            {
                                key: 'photo_count',
                                label: 'Photos',
                                sortable: true,
                                render: (gallery) => gallery.photo_count ?? '—',
                            },
                            {
                                key: 'flickr_gallery_id',
                                label: 'Gallery ID',
                                sortable: true,
                                render: (gallery) => (
                                    <FlickrGalleryIdLinks
                                        galleryId={gallery.flickr_gallery_id}
                                        ownerNsid={gallery.owner_nsid}
                                        title={gallery.title}
                                        showSubtext={false}
                                    />
                                ),
                            },
                            {
                                key: 'owner_nsid',
                                label: 'Owner',
                                sortable: true,
                                render: (gallery) => (
                                    <ContactNsidLinks
                                        nsid={gallery.owner_nsid}
                                        accountPublicId={account?.public_id}
                                    />
                                ),
                            },
                        ]}
                        data={galleries}
                        rowKey={(gallery) => String(gallery.id)}
                        sortKey={sortKey}
                        sortDirection={sortDirection}
                        onSortChange={handleSortChange}
                        emptyMessage="No galleries found."
                        actionsColumn={
                            account?.public_id
                                ? (gallery) => (
                                      <CrawlActionBar
                                          scope="contact"
                                          accountPublicId={account.public_id}
                                          contactNsid={gallery.owner_nsid}
                                          subjectLabel={crawlSubjectForContact({
                                              nsid: gallery.owner_nsid,
                                              username: null,
                                              realname: gallery.title,
                                          })}
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
                </PageShellCanvas>
            </PageShell>
        </AppLayout>
    );
}
