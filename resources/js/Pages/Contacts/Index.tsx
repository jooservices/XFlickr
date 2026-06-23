import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';

import Breadcrumbs from '@/Components/Breadcrumbs';
import type { BulkAction } from '@/Components/BulkActionBar';
import ContactCatalogCell from '@/Components/ContactCatalogCell';
import ContactDownloadCell from '@/Components/ContactDownloadCell';
import ContactNsidLinks from '@/Components/ContactNsidLinks';
import ContactsSearchFilter from '@/Components/ContactsSearchFilter';
import CrawlActionBar from '@/Components/CrawlActionBar';
import CrawlTypeMenu, {
    bulkCrawlActionIcon,
    bulkDownloadActionIcon,
    bulkUploadActionIcon,
} from '@/Components/CrawlTypeMenu';
import DataTable from '@/Components/DataTable';
import PageHeading from '@/Components/PageHeading';
import { useTableSelection } from '@/hooks/useTableSelection';
import AppLayout from '@/Layouts/AppLayout';
import { apiGet } from '@/lib/apiClient';
import { flickrAccountPageCrumbs } from '@/lib/breadcrumbs';
import { catalogOwnerUrl } from '@/lib/catalog';
import { CONTACT_CATALOG_COLUMNS } from '@/lib/contactCatalog';
import { flickrAccountPath, flickrApiAccountPath } from '@/lib/flickrAccount';
import type { ContactListItem, CrawlType, CrawlTypeState, FlickrAccount, PageProps, PaginatedMeta } from '@/types';

interface Props extends PageProps {
    account: FlickrAccount;
    contacts: ContactListItem[];
    meta: PaginatedMeta;
    filters: {
        search: string;
        sort: string;
        direction: 'asc' | 'desc';
    };
}

function CountLink({
    href,
    count,
    state,
}: {
    href: string;
    count: number;
    state?: CrawlTypeState;
}) {
    if (state?.processing) {
        return <ContactCatalogCell count={count} state={state} />;
    }

    return (
        <Link href={href} className="tabular-nums text-cyan-700 hover:underline">
            {count}
        </Link>
    );
}

function isContactSelectable(contact: ContactListItem): boolean {
    if (contact.download_state?.processing) {
        return false;
    }

    return !Object.values(contact.crawl_state ?? {}).some((state) => state.processing);
}

export default function ContactsIndex({ account, contacts: initialContacts, meta, filters }: Props) {
    const [draft, setDraft] = useState(filters.search);
    const [contacts, setContacts] = useState(initialContacts);

    useEffect(() => {
        setContacts(initialContacts);
    }, [initialContacts]);

    useEffect(() => {
        setDraft(filters.search);
    }, [filters.search]);

    const hasActiveSearch = filters.search.trim().length > 0;

    const applySearch = useCallback(() => {
        router.get(
            flickrAccountPath(account.public_id, '/contacts'),
            {
                search: draft || undefined,
                sort: filters.sort,
                direction: filters.direction,
            },
            { preserveScroll: true, replace: true },
        );
    }, [account.public_id, draft, filters.sort, filters.direction]);

    const clearSearch = useCallback(() => {
        setDraft('');
        router.get(
            flickrAccountPath(account.public_id, '/contacts'),
            {
                sort: filters.sort,
                direction: filters.direction,
            },
            { preserveScroll: true, replace: true },
        );
    }, [account.public_id, filters.sort, filters.direction]);

    const selectionClearKey = `${filters.search}|${filters.sort}|${filters.direction}|${meta.current_page}`;

    const selection = useTableSelection({
        rowKey: (contact) => contact.nsid,
        rows: contacts,
        isRowSelectable: isContactSelectable,
        clearWhen: selectionClearKey,
    });

    const contactNsids = useMemo(() => contacts.map((contact) => contact.nsid).join(','), [contacts]);

    const hasActiveOperations = useMemo(
        () =>
            contacts.some(
                (contact) =>
                    contact.download_state?.processing ||
                    Object.values(contact.crawl_state ?? {}).some((state) => state.processing),
            ),
        [contacts],
    );

    useEffect(() => {
        if (!hasActiveOperations || contactNsids === '') {
            return;
        }

        const controller = new AbortController();

        const poll = () => {
            void apiGet<{ contacts: ContactListItem[] }>(
                flickrApiAccountPath(account.public_id, '/contacts/progress'),
                { params: { nsids: contactNsids }, signal: controller.signal },
            )
                .then((data) => {
                    setContacts((current) => {
                        const updates = new Map(data.contacts.map((contact) => [contact.nsid, contact]));

                        return current.map((contact) => updates.get(contact.nsid) ?? contact);
                    });
                })
                .catch(() => undefined);
        };

        poll();
        const interval = setInterval(poll, 3000);

        return () => {
            controller.abort();
            clearInterval(interval);
        };
    }, [hasActiveOperations, contactNsids, account.public_id]);

    const postBulk = useCallback(
        (url: string, data: { contact_nsids: string[]; types?: CrawlType[] }) => {
            router.post(url, data, {
                preserveScroll: true,
                onSuccess: () => selection.clear(),
            });
        },
        [selection],
    );

    const bulkActions = useMemo<BulkAction<ContactListItem>[]>(
        () => [
            {
                id: 'download',
                label: 'Download',
                icon: bulkDownloadActionIcon(),
                onAction: ({ selectedKeys }) => {
                    postBulk(flickrAccountPath(account.public_id, '/download'), {
                        contact_nsids: selectedKeys,
                    });
                },
            },
            {
                id: 'crawl',
                label: 'Crawl',
                icon: bulkCrawlActionIcon(),
                menu: () => (
                    <CrawlTypeMenu
                        onSelect={(types: CrawlType[]) => {
                            postBulk(flickrAccountPath(account.public_id, '/contacts/crawl'), {
                                contact_nsids: [...selection.selectedKeys],
                                types,
                            });
                        }}
                    />
                ),
            },
            {
                id: 'upload',
                label: 'Upload',
                icon: bulkUploadActionIcon(),
                onAction: ({ selectedKeys }) => {
                    postBulk(flickrAccountPath(account.public_id, '/upload'), {
                        contact_nsids: selectedKeys,
                    });
                },
            },
        ],
        [account.public_id, postBulk, selection.selectedKeys],
    );

    const goToPage = (page: number) => {
        router.get(
            flickrAccountPath(account.public_id, '/contacts'),
            {
                search: filters.search || undefined,
                sort: filters.sort,
                direction: filters.direction,
                page: page > 1 ? page : undefined,
            },
            { preserveScroll: true },
        );
    };

    const handleSortChange = (key: string, direction: 'asc' | 'desc') => {
        router.get(
            flickrAccountPath(account.public_id, '/contacts'),
            {
                search: filters.search || undefined,
                sort: key,
                direction,
            },
            { preserveScroll: true },
        );
    };

    const catalogPaths: Record<string, string> = {
        photos: '/photos',
        favorites: '/favorites',
        photosets: '/photosets',
        galleries: '/galleries',
    };

    return (
        <AppLayout>
            <Head title={`Contacts — ${account.username ?? account.nsid}`} />

            <div className="space-y-6">
                <PageHeading
                    breadcrumbs={<Breadcrumbs items={flickrAccountPageCrumbs(account)} />}
                    title="Contacts"
                    subtitle={`${meta.total} contacts linked to this account.`}
                />

                <ContactsSearchFilter
                    accountPublicId={account.public_id}
                    value={draft}
                    onChange={setDraft}
                    onSubmit={applySearch}
                    onClear={hasActiveSearch ? clearSearch : undefined}
                />

                <DataTable
                    columns={[
                        {
                            key: 'nsid',
                            label: 'NSID',
                            sortable: true,
                            render: (contact) => (
                                <ContactNsidLinks
                                    nsid={contact.nsid}
                                    accountPublicId={account.public_id}
                                    username={contact.username}
                                    realname={contact.realname}
                                />
                            ),
                        },
                        ...CONTACT_CATALOG_COLUMNS.map((column) => ({
                            key: column.key,
                            label: column.label,
                            sortable: true,
                            render: (contact: ContactListItem) => (
                                <CountLink
                                    href={catalogOwnerUrl(catalogPaths[column.key] ?? '/photos', contact.nsid)}
                                    count={contact[column.countKey]}
                                    state={contact.crawl_state?.[column.key]}
                                />
                            ),
                        })),
                        {
                            key: 'downloads_count',
                            label: 'Downloads',
                            sortable: true,
                            render: (contact) => (
                                <ContactDownloadCell
                                    count={contact.downloads_count}
                                    failedCount={contact.downloads_failed_count}
                                    state={contact.download_state}
                                />
                            ),
                        },
                    ]}
                    data={contacts}
                    rowKey={(contact) => contact.nsid}
                    sortKey={filters.sort}
                    sortDirection={filters.direction}
                    onSortChange={handleSortChange}
                    emptyMessage={
                        filters.search
                            ? 'No contacts match your search.'
                            : 'No contacts yet. Run a contacts crawl first.'
                    }
                    actionsColumn={(contact) => (
                        <CrawlActionBar
                            scope="contact"
                            accountPublicId={account.public_id}
                            contactNsid={contact.nsid}
                            typeStates={contact.crawl_state ?? {}}
                        />
                    )}
                    meta={meta}
                    onPageChange={goToPage}
                    selection={selection.tableSelection}
                    bulkActions={bulkActions}
                    onBulkClear={selection.clear}
                />
            </div>
        </AppLayout>
    );
}
