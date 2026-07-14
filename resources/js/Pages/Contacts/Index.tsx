import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';

import ContactCatalogCell from '@/Components/Contacts/CatalogCell';
import ContactAnnotationActions from '@/Components/Contacts/ContactAnnotationActions';
import ContactViewModeToggle, { type ContactViewMode } from '@/Components/Contacts/ContactViewModeToggle';
import ContactDownloadCell from '@/Components/Contacts/DownloadCell';
import ContactGraphShell from '@/Components/Contacts/Graph/ContactGraphShell';
import ImportContactUrlModal from '@/Components/Contacts/ImportContactUrlModal';
import ContactNsidLinks from '@/Components/Contacts/NsidLinks';
import ContactsSearchFilter from '@/Components/Contacts/SearchFilter';
import CrawlActionBar from '@/Components/Flickr/CrawlActionBar';
import CrawlTypeMenu, {
    bulkCrawlActionIcon,
    bulkDownloadActionIcon,
    bulkUploadActionIcon,
} from '@/Components/Flickr/CrawlTypeMenu';
import { PageShell, PageShellCanvas, PageShellControlBar, PageShellIdentity } from '@/Components/Layout/page-shell';
import type { BulkAction } from '@/Components/ui/BulkActionBar';
import Button from '@/Components/ui/Button';
import DataTable from '@/Components/ui/DataTable';
import { usePolledResource } from '@/hooks/usePolledResource';
import { useTableSelection } from '@/hooks/useTableSelection';
import AppLayout from '@/Layouts/AppLayout';
import { accountLabel, flickrAccountPageCrumbs } from '@/lib/breadcrumbs';
import { catalogOwnerUrl } from '@/lib/catalog';
import { CONTACT_CATALOG_COLUMNS } from '@/lib/contactCatalog';
import { crawlSubjectForContact } from '@/lib/crawlSubject';
import { flickrAccountPath, flickrApiAccountPath } from '@/lib/flickrAccount';
import type {
    ContactAnnotationPayload,
    ContactListItem,
    CrawlType,
    CrawlTypeState,
    FlickrAccount,
    PageProps,
    PaginatedMeta,
} from '@/types';

interface Props extends PageProps {
    account: FlickrAccount;
    contacts: ContactListItem[];
    meta: PaginatedMeta;
    filters: {
        search: string;
        sort: string;
        direction: 'asc' | 'desc';
        starred_only?: boolean;
        view?: ContactViewMode;
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
    const [importOpen, setImportOpen] = useState(false);
    const viewMode: ContactViewMode = filters.view === 'graph' ? 'graph' : 'table';
    const starredOnly = filters.starred_only ?? false;

    useEffect(() => {
        setContacts(initialContacts);
    }, [initialContacts]);

    useEffect(() => {
        setDraft(filters.search);
    }, [filters.search]);

    const hasActiveSearch = filters.search.trim().length > 0;

    const navigateWithFilters = useCallback(
        (overrides: Partial<{ search: string; sort: string; direction: 'asc' | 'desc'; starred_only: boolean; view: ContactViewMode; page?: number }>) => {
            router.get(
                flickrAccountPath(account.public_id, '/contacts'),
                {
                    search: (overrides.search ?? filters.search) || undefined,
                    sort: overrides.sort ?? filters.sort,
                    direction: overrides.direction ?? filters.direction,
                    starred_only: (overrides.starred_only ?? starredOnly) || undefined,
                    view: (overrides.view ?? viewMode) === 'table' ? undefined : overrides.view ?? viewMode,
                    page: overrides.page && overrides.page > 1 ? overrides.page : undefined,
                },
                { preserveScroll: true, replace: true },
            );
        },
        [account.public_id, filters.search, filters.sort, filters.direction, starredOnly, viewMode],
    );

    const applySearch = useCallback(() => {
        navigateWithFilters({ search: draft, page: 1 });
    }, [draft, navigateWithFilters]);

    const clearSearch = useCallback(() => {
        setDraft('');
        navigateWithFilters({ search: '', page: 1 });
    }, [navigateWithFilters]);

    const selectionClearKey = `${filters.search}|${filters.sort}|${filters.direction}|${starredOnly}|${viewMode}`;

    const selection = useTableSelection({
        rowKey: (contact) => contact.nsid,
        rows: contacts,
        isRowSelectable: isContactSelectable,
        clearWhen: selectionClearKey,
        matchingTotal: meta.total,
        allowSelectMatching: viewMode === 'table',
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

    const shouldPollProgress = hasActiveOperations && contactNsids !== '';
    const { data: progressData } = usePolledResource<{ data: { contacts: ContactListItem[] } }>(
        shouldPollProgress ? flickrApiAccountPath(account.public_id, '/contacts/progress') : null,
        {
            intervalMs: 3000,
            enabled: shouldPollProgress,
            params: { nsids: contactNsids },
        },
    );

    useEffect(() => {
        const updates = progressData?.data.contacts;

        if (!updates) {
            return;
        }

        setContacts((current) => {
            const byNsid = new Map(updates.map((contact) => [contact.nsid, contact]));

            return current.map((contact) => byNsid.get(contact.nsid) ?? contact);
        });
    }, [progressData]);

    const postBulk = useCallback(
        (
            url: string,
            data: {
                contact_nsids?: string[];
                types?: CrawlType[];
                select_all?: boolean;
                search?: string;
                starred_only?: boolean;
            },
        ) => {
            router.post(url, data, {
                preserveScroll: true,
                onSuccess: () => selection.clear(),
            });
        },
        [selection],
    );

    const bulkPayload = useCallback(
        (selectedKeys: string[], isMatching: boolean) => {
            if (isMatching) {
                return {
                    select_all: true as const,
                    search: filters.search || undefined,
                    starred_only: starredOnly || undefined,
                };
            }

            return { contact_nsids: selectedKeys };
        },
        [filters.search, starredOnly],
    );

    const bulkActions = useMemo<BulkAction<ContactListItem>[]>(
        () => [
            {
                id: 'download',
                label: 'Download',
                icon: bulkDownloadActionIcon(),
                onAction: ({ selectedKeys, isMatching }) => {
                    postBulk(flickrAccountPath(account.public_id, '/download'), bulkPayload(selectedKeys, isMatching));
                },
            },
            {
                id: 'crawl',
                label: 'Crawl',
                icon: bulkCrawlActionIcon(),
                menu: ({ selectedKeys, isMatching }) => (
                    <CrawlTypeMenu
                        onSelect={(types: CrawlType[]) => {
                            postBulk(flickrAccountPath(account.public_id, '/contacts/crawl'), {
                                ...bulkPayload(selectedKeys, isMatching),
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
                onAction: ({ selectedKeys, isMatching }) => {
                    postBulk(flickrAccountPath(account.public_id, '/upload'), bulkPayload(selectedKeys, isMatching));
                },
            },
        ],
        [account.public_id, bulkPayload, postBulk],
    );

    const goToPage = (page: number) => {
        navigateWithFilters({ page });
    };

    const handleSortChange = (key: string, direction: 'asc' | 'desc') => {
        navigateWithFilters({ sort: key, direction, page: 1 });
    };

    const handleAnnotationUpdated = useCallback((contactNsid: string, payload: ContactAnnotationPayload) => {
        setContacts((current) =>
            current.map((contact) =>
                contact.nsid === contactNsid
                    ? {
                          ...contact,
                          starred: payload.starred,
                          note: payload.note,
                          note_preview:
                              payload.note && payload.note.length > 80
                                  ? `${payload.note.slice(0, 77)}...`
                                  : payload.note,
                      }
                    : contact,
            ),
        );
    }, []);

    const catalogPaths: Record<string, string> = {
        photos: '/photos',
        favorites: '/favorites',
        photosets: '/photosets',
        galleries: '/galleries',
    };

    return (
        <AppLayout>
            <Head title={`Contacts — ${account.username ?? account.nsid}`} />

            {viewMode === 'graph' ? (
                <ContactGraphShell
                    accountPublicId={account.public_id}
                    rootNsid={account.nsid}
                    accountLabel={accountLabel(account)}
                    onExit={() => navigateWithFilters({ view: 'table' })}
                />
            ) : null}

            {viewMode === 'table' ? (
            <PageShell data-testid="contacts-page">
                <PageShellIdentity
                    breadcrumbs={[...flickrAccountPageCrumbs(account), { label: 'Contacts' }]}
                    title="Contacts"
                    subtitle={`${meta.total} contacts linked to this account.`}
                />

                <PageShellControlBar
                    filters={
                        <ContactsSearchFilter
                            accountPublicId={account.public_id}
                            value={draft}
                            onChange={setDraft}
                            onSubmit={applySearch}
                            onClear={hasActiveSearch ? clearSearch : undefined}
                        />
                    }
                    actions={
                        <div className="flex flex-wrap items-center gap-3">
                            <Button type="button" variant="secondary" size="sm" onClick={() => setImportOpen(true)}>
                                Import from URL
                            </Button>
                            <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={starredOnly}
                                    onChange={(event) =>
                                        navigateWithFilters({ starred_only: event.target.checked, page: 1 })
                                    }
                                    className="rounded border-slate-300 text-cyan-700 focus:ring-cyan-600"
                                />
                                Starred only
                            </label>
                            <ContactViewModeToggle
                                value={viewMode}
                                onChange={(mode) => navigateWithFilters({ view: mode, page: 1 })}
                            />
                        </div>
                    }
                />

                <ImportContactUrlModal
                    accountPublicId={account.public_id}
                    open={importOpen}
                    onClose={() => setImportOpen(false)}
                />

                <PageShellCanvas className="space-y-6" variant="plain">
                <DataTable
                        columns={[
                            {
                                key: 'starred',
                                label: '',
                                render: (contact) => (
                                    <ContactAnnotationActions
                                        accountPublicId={account.public_id}
                                        contactNsid={contact.nsid}
                                        starred={contact.starred ?? false}
                                        note={contact.note ?? null}
                                        onUpdated={(payload) => handleAnnotationUpdated(contact.nsid, payload)}
                                    />
                                ),
                            },
                            {
                                key: 'nsid',
                                label: 'NSID',
                                sortable: true,
                                render: (contact) => (
                                    <div className="space-y-1">
                                        <ContactNsidLinks
                                            nsid={contact.nsid}
                                            accountPublicId={account.public_id}
                                            username={contact.username}
                                            realname={contact.realname}
                                        />
                                        {contact.note_preview ? (
                                            <p className="max-w-xs truncate text-xs text-slate-500">{contact.note_preview}</p>
                                        ) : null}
                                    </div>
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
                            starredOnly
                                ? 'No starred contacts yet.'
                                : filters.search
                                  ? 'No contacts match your search.'
                                  : 'No contacts yet. Run a contacts crawl first.'
                        }
                        actionsColumn={(contact) => (
                            <CrawlActionBar
                                scope="contact"
                                accountPublicId={account.public_id}
                                contactNsid={contact.nsid}
                                subjectLabel={crawlSubjectForContact(contact)}
                                typeStates={contact.crawl_state ?? {}}
                            />
                        )}
                        meta={meta}
                        onPageChange={goToPage}
                        selection={selection.tableSelection}
                        bulkActions={bulkActions}
                        onBulkClear={selection.clear}
                        matchingLabel="contacts"
                    />
                </PageShellCanvas>
            </PageShell>
            ) : null}
        </AppLayout>
    );
}
