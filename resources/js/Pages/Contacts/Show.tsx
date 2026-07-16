import { Head } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';

import CatalogStatCard from '@/Components/Catalog/StatCard';
import ContactPhotoStrip from '@/Components/Contacts/ContactPhotoStrip';
import ContactSwitcher from '@/Components/Contacts/Switcher';
import CrawlActionBar from '@/Components/Flickr/CrawlActionBar';
import { PageShell, PageShellCanvas, PageShellIdentity } from '@/Components/layout/page-shell';
import ActionBar from '@/Components/ui/ActionBar';
import Card from '@/Components/ui/Card';
import AppLayout from '@/Layouts/AppLayout';
import { flickrContactShowCrumbs } from '@/lib/breadcrumbs';
import { flickrPeopleUrl } from '@/lib/catalog';
import { crawlSubjectForContact } from '@/lib/crawlSubject';
import { formatCount } from '@/lib/format';
import type { Contact, ContactCatalogStats, ContactCrawlState, FlickrAccount, PageProps } from '@/types';

interface Props extends PageProps {
    account: FlickrAccount;
    contact: Contact;
    catalog_stats: ContactCatalogStats;
    crawl_state: ContactCrawlState;
}

const CURATED_PAYLOAD_KEYS = new Set(['nsid', 'username', 'realname', 'friend', 'family']);

function detailRows(contact: Contact): Array<{ label: string; value: string }> {
    return [
        { label: 'NSID', value: contact.nsid },
        { label: 'Username', value: contact.username ? `@${contact.username}` : '—' },
        { label: 'Real name', value: contact.realname ?? '—' },
        { label: 'Friend', value: contact.friend ? 'Yes' : 'No' },
        { label: 'Family', value: contact.family ? 'Yes' : 'No' },
    ];
}

function technicalRows(contact: Contact): Array<{ label: string; value: string }> {
    const rows: Array<{ label: string; value: string }> = [];
    const payload = contact.raw_payload ?? {};

    for (const [key, value] of Object.entries(payload)) {
        if (CURATED_PAYLOAD_KEYS.has(key)) {
            continue;
        }

        if (value === null || value === undefined || typeof value === 'object') {
            continue;
        }

        rows.push({ label: key, value: String(value) });
    }

    return rows;
}

function identitySubtitle(contact: Contact): string {
    const parts: string[] = [];

    if (contact.username) {
        parts.push(`@${contact.username}`);
    }

    parts.push(contact.nsid);

    return parts.join(' · ');
}

export default function ContactsShow({ account, contact, catalog_stats, crawl_state }: Props) {
    const displayName = contact.realname || contact.username || contact.nsid;
    const curated = detailRows(contact);
    const technical = technicalRows(contact);
    const relationshipChips = [
        contact.friend ? 'Friend' : null,
        contact.family ? 'Family' : null,
    ].filter((chip): chip is string => chip !== null);

    return (
        <AppLayout>
            <Head title={displayName} />

            <PageShell>
                <PageShellIdentity
                    breadcrumbs={flickrContactShowCrumbs(account, displayName)}
                    title={displayName}
                    subtitle={identitySubtitle(contact)}
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
                                subjectLabel={crawlSubjectForContact(contact)}
                                typeStates={crawl_state}
                                size="md"
                            />
                        </ActionBar>
                    }
                />

                <PageShellCanvas className="space-y-6" variant="plain">
                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <a
                            href={flickrPeopleUrl(contact.nsid)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1 font-medium text-cyan-700 hover:underline"
                        >
                            Open on Flickr
                            <ExternalLink className="size-3.5" aria-hidden />
                        </a>
                        {relationshipChips.map((chip) => (
                            <span
                                key={chip}
                                className="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
                            >
                                {chip}
                            </span>
                        ))}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <CatalogStatCard
                            title="Photos"
                            dbCount={catalog_stats.photos.db}
                            catalogPath="/photos"
                            ownerNsid={contact.nsid}
                            sublines={
                                <>
                                    <span>With sizes: {formatCount(catalog_stats.photos.with_sizes ?? 0)}</span>
                                    <span className="mx-1">·</span>
                                    <span>In API: {formatCount(catalog_stats.photos.in_api)}</span>
                                </>
                            }
                        />
                        <CatalogStatCard
                            title="Photosets"
                            dbCount={catalog_stats.photosets.db}
                            catalogPath="/photosets"
                            ownerNsid={contact.nsid}
                            sublines={<span>In API: {formatCount(catalog_stats.photosets.in_api)}</span>}
                        />
                        <CatalogStatCard
                            title="Favorites"
                            dbCount={catalog_stats.favorites.db}
                            catalogPath="/favorites"
                            ownerNsid={contact.nsid}
                            sublines={<span>In API: {formatCount(catalog_stats.favorites.in_api)}</span>}
                        />
                        <CatalogStatCard
                            title="Galleries"
                            dbCount={catalog_stats.galleries.db}
                            catalogPath="/galleries"
                            ownerNsid={contact.nsid}
                            sublines={<span>In API: {formatCount(catalog_stats.galleries.in_api)}</span>}
                        />
                    </div>

                    <Card showFooter={false}>
                        <ContactPhotoStrip
                            ownerNsid={contact.nsid}
                            photosCount={catalog_stats.photos.db}
                        />
                    </Card>

                    <Card title="Detail" showFooter={false}>
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            {curated.map((row) => (
                                <div key={row.label} className="flex justify-between gap-4 border-b border-slate-50 pb-2">
                                    <dt className="text-slate-500">{row.label}</dt>
                                    <dd className="text-right font-medium text-slate-900">{row.value}</dd>
                                </div>
                            ))}
                        </dl>

                        {technical.length > 0 ? (
                            <details className="mt-4 rounded-md border border-slate-100 bg-slate-50/60 px-3 py-2">
                                <summary className="cursor-pointer text-sm font-medium text-slate-700">
                                    Technical fields
                                </summary>
                                <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                    {technical.map((row) => (
                                        <div
                                            key={row.label}
                                            className="flex justify-between gap-4 border-b border-slate-100 pb-2"
                                        >
                                            <dt className="font-mono text-xs text-slate-500">{row.label}</dt>
                                            <dd className="text-right font-medium text-slate-900">{row.value}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </details>
                        ) : null}
                    </Card>
                </PageShellCanvas>
            </PageShell>
        </AppLayout>
    );
}
