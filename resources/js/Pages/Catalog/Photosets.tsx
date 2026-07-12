import { Head } from '@inertiajs/react';

import CatalogOwnerNsidFilter from '@/Components/CatalogOwnerNsidFilter';
import ContactNsidLinks from '@/Components/ContactNsidLinks';
import CrawlActionBar from '@/Components/CrawlActionBar';
import DataTable from '@/Components/DataTable';
import FlickrPhotosetIdLinks from '@/Components/FlickrPhotosetIdLinks';
import { PageShell, PageShellCanvas, PageShellControlBar, PageShellIdentity } from '@/Components/layout/page-shell';
import Thumbnail from '@/Components/Thumbnail';
import { useCatalogOwnerNsidTable } from '@/hooks/useCatalogOwnerNsidTable';
import AppLayout from '@/Layouts/AppLayout';
import { catalogPageCrumbs } from '@/lib/breadcrumbs';
import { catalogPhotosetShowPath } from '@/lib/catalog';
import { crawlSubjectForContact } from '@/lib/crawlSubject';
import { flickrCollectionThumbnailUrl } from '@/lib/flickrCollection';
import type { FlickrAccount, PageProps, Photoset } from '@/types';

interface Props extends PageProps {
    account: FlickrAccount | null;
}

export default function CatalogPhotosets({ account }: Props) {
    const { data: photosets, meta, setPage, loading, sortKey, sortDirection, handleSortChange, filterFormProps } =
        useCatalogOwnerNsidTable<Photoset>('owner_nsid', {
            fetchPath: '/api/v1/flickr/catalog/photosets',
            initialSort: 'id',
            initialDirection: 'desc',
        });

    return (
        <AppLayout>
            <Head title="Photosets" />

            <PageShell>
                <PageShellIdentity
                    breadcrumbs={catalogPageCrumbs('Photosets', account)}
                    title="Photosets"
                    subtitle="Browse crawled photosets in the catalog."
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
                                render: (photoset) => (
                                    <Thumbnail
                                        url={flickrCollectionThumbnailUrl(photoset)}
                                        alt={photoset.title || 'Photoset'}
                                        linkHref={catalogPhotosetShowPath(photoset.id, account?.public_id)}
                                    />
                                ),
                            },
                            {
                                key: 'title',
                                label: 'Title',
                                sortable: true,
                                render: (photoset) => photoset.title || 'Untitled',
                            },
                            {
                                key: 'photo_count',
                                label: 'Photos',
                                sortable: true,
                                render: (photoset) => photoset.photo_count ?? '—',
                            },
                            {
                                key: 'flickr_photoset_id',
                                label: 'Photoset ID',
                                sortable: true,
                                render: (photoset) => (
                                    <FlickrPhotosetIdLinks
                                        photosetId={photoset.flickr_photoset_id}
                                        ownerNsid={photoset.owner_nsid}
                                        title={photoset.title}
                                        showSubtext={false}
                                    />
                                ),
                            },
                            {
                                key: 'owner_nsid',
                                label: 'Owner',
                                sortable: true,
                                render: (photoset) => (
                                    <ContactNsidLinks
                                        nsid={photoset.owner_nsid}
                                        accountPublicId={account?.public_id}
                                    />
                                ),
                            },
                        ]}
                        data={photosets}
                        rowKey={(photoset) => String(photoset.id)}
                        sortKey={sortKey}
                        sortDirection={sortDirection}
                        onSortChange={handleSortChange}
                        emptyMessage="No photosets found."
                        actionsColumn={
                            account?.public_id
                                ? (photoset) => (
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
