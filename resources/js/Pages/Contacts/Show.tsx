import { Head } from '@inertiajs/react';

import ActionBar from '@/Components/ActionBar';
import Breadcrumbs from '@/Components/Breadcrumbs';
import Card from '@/Components/Card';
import CatalogStatCard from '@/Components/CatalogStatCard';
import ContactSwitcher from '@/Components/ContactSwitcher';
import CrawlActionBar from '@/Components/CrawlActionBar';
import PageHeading from '@/Components/PageHeading';
import AppLayout from '@/Layouts/AppLayout';
import { flickrContactShowCrumbs } from '@/lib/breadcrumbs';
import type { Contact, ContactCatalogStats, ContactCrawlState, FlickrAccount, PageProps } from '@/types';

interface Props extends PageProps {
    account: FlickrAccount;
    contact: Contact;
    catalog_stats: ContactCatalogStats;
    crawl_state: ContactCrawlState;
}

function formatInApi(value: number | null): string {
    return value === null ? '—' : value.toLocaleString();
}

function detailRows(contact: Contact): Array<{ label: string; value: string }> {
    const rows: Array<{ label: string; value: string }> = [
        { label: 'NSID', value: contact.nsid },
        { label: 'Username', value: contact.username ? `@${contact.username}` : '—' },
        { label: 'Real name', value: contact.realname ?? '—' },
        { label: 'Friend', value: contact.friend ? 'Yes' : 'No' },
        { label: 'Family', value: contact.family ? 'Yes' : 'No' },
    ];

    const payload = contact.raw_payload ?? {};

    for (const [key, value] of Object.entries(payload)) {
        if (['nsid', 'username', 'realname', 'friend', 'family'].includes(key)) {
            continue;
        }

        if (value === null || value === undefined || typeof value === 'object') {
            continue;
        }

        rows.push({ label: key, value: String(value) });
    }

    return rows;
}

export default function ContactsShow({ account, contact, catalog_stats, crawl_state }: Props) {
    const displayName = contact.realname || contact.username || contact.nsid;

    return (
        <AppLayout>
            <Head title={displayName} />

            <div className="space-y-6">
                <PageHeading
                    breadcrumbs={
                        <Breadcrumbs
                            items={flickrContactShowCrumbs(account, contact.username ?? contact.nsid)}
                        />
                    }
                    title={<span className="font-mono">{contact.nsid}</span>}
                    subtitle={`${contact.realname || contact.username || contact.nsid}${
                        contact.username ? ` · @${contact.username}` : ''
                    }`}
                    actions={
                        <ActionBar>
                            <ContactSwitcher
                                accountPublicId={account.public_id}
                                currentContactNsid={contact.nsid}
                                currentLabel={contact.username ?? contact.nsid}
                            />
                            <CrawlActionBar
                                scope="contact"
                                accountPublicId={account.public_id}
                                contactNsid={contact.nsid}
                                typeStates={crawl_state}
                                size="md"
                            />
                        </ActionBar>
                    }
                />

                <Card title="Detail">
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        {detailRows(contact).map((row) => (
                            <div key={row.label} className="flex justify-between gap-4 border-b border-slate-50 pb-2">
                                <dt className="text-slate-500">{row.label}</dt>
                                <dd className="text-right font-medium text-slate-900">{row.value}</dd>
                            </div>
                        ))}
                    </dl>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2">
                    <CatalogStatCard
                        title="Photos"
                        dbCount={catalog_stats.photos.db}
                        catalogPath="/photos"
                        ownerNsid={contact.nsid}
                        sublines={
                            <>
                                <span>With sizes: {catalog_stats.photos.with_sizes?.toLocaleString() ?? '0'}</span>
                                <span className="mx-1">·</span>
                                <span>In API: {formatInApi(catalog_stats.photos.in_api)}</span>
                            </>
                        }
                    />
                    <CatalogStatCard
                        title="Photosets"
                        dbCount={catalog_stats.photosets.db}
                        catalogPath="/photosets"
                        ownerNsid={contact.nsid}
                        sublines={<span>In API: {formatInApi(catalog_stats.photosets.in_api)}</span>}
                    />
                    <CatalogStatCard
                        title="Favorites"
                        dbCount={catalog_stats.favorites.db}
                        catalogPath="/favorites"
                        ownerNsid={contact.nsid}
                        sublines={<span>In API: {formatInApi(catalog_stats.favorites.in_api)}</span>}
                    />
                    <CatalogStatCard
                        title="Galleries"
                        dbCount={catalog_stats.galleries.db}
                        catalogPath="/galleries"
                        ownerNsid={contact.nsid}
                        sublines={<span>In API: {formatInApi(catalog_stats.galleries.in_api)}</span>}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
