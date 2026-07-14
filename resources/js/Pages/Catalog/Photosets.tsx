import { Head } from '@inertiajs/react';

import CatalogOwnerNsidFilter from '@/Components/Catalog/OwnerNsidFilter';
import ContactNsidLinks from '@/Components/Contacts/NsidLinks';
import CrawlActionBar from '@/Components/Flickr/CrawlActionBar';
import FlickrPhotosetIdLinks from '@/Components/Flickr/PhotosetIdLinks';
import { PageShell, PageShellCanvas, PageShellControlBar, PageShellIdentity } from '@/Components/Layout/page-shell';
import DataTable from '@/Components/ui/DataTable';
import EmptyState from '@/Components/ui/EmptyState';
import Thumbnail from '@/Components/ui/Thumbnail';
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
    const {
        data: photosets,
        meta,
        setPage,
        loading,
        sortKey,
        sortDirection,
        handleSortChange,
        filterFormProps,
        appliedOwnerNsid,
    } = useCatalogOwnerNsidTable<Photoset>('owner_nsid', {
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
                    <DataTable
                        busy={loading}
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
                        emptyMessage={
                            <EmptyState
                                title="No photosets found."
                                description={
                                    appliedOwnerNsid.trim()
                                        ? `No photosets match owner NSID “${appliedOwnerNsid.trim()}”.`
                                        : 'Crawl photosets for a contact to populate this catalog.'
                                }
                            />
                        }
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
                </PageShellCanvas>
            </PageShell>
        </AppLayout>
    );
}
