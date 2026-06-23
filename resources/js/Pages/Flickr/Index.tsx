import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';

import Breadcrumbs from '@/Components/Breadcrumbs';
import Button from '@/Components/Button';
import CrawlActionBar from '@/Components/CrawlActionBar';
import DataTable from '@/Components/DataTable';
import PageHeading from '@/Components/PageHeading';
import RateLimitMeter from '@/Components/RateLimitMeter';
import { useTableSort } from '@/hooks/useTableSort';
import AppLayout from '@/Layouts/AppLayout';
import { apiGet } from '@/lib/apiClient';
import { flickrRootCrumb } from '@/lib/breadcrumbs';
import { flickrAccountPath, flickrApiAccountPath } from '@/lib/flickrAccount';
import { sortClientData } from '@/lib/tableSort';
import type { CrawlSummary, FlickrAccount, PageProps } from '@/types';

interface Props extends PageProps {
    accounts: FlickrAccount[];
}

function accountSortValue(
    account: FlickrAccount,
    key: string,
    summaries: Record<string, CrawlSummary>,
): string | number {
    switch (key) {
        case 'account':
            return account.fullname || account.username || account.nsid;
        case 'status':
            return account.is_active ? 1 : 0;
        case 'quota':
            return summaries[account.nsid]?.rate_limit?.requests_remaining ?? -1;
        default:
            return account.nsid;
    }
}

export default function FlickrIndex({ accounts }: Props) {
    const [summaries, setSummaries] = useState<Record<string, CrawlSummary>>({});
    const { sortKey, sortDirection, handleSortChange } = useTableSort({
        initialSort: 'account',
        initialDirection: 'asc',
    });

    const sortedAccounts = useMemo(
        () => sortClientData(accounts, sortKey, sortDirection, (account, key) => accountSortValue(account, key, summaries)),
        [accounts, sortKey, sortDirection, summaries],
    );

    const loadSummaries = useCallback(async () => {
        const entries = await Promise.all(
            accounts.map(async (account) => {
                try {
                    const data = await apiGet<CrawlSummary>(
                        flickrApiAccountPath(account.public_id, '/crawl/summary'),
                    );

                    return [account.nsid, data] as const;
                } catch {
                    return [account.nsid, null] as const;
                }
            }),
        );

        setSummaries(
            Object.fromEntries(entries.filter(([, summary]) => summary !== null).map(([nsid, summary]) => [nsid, summary!])),
        );
    }, [accounts]);

    useEffect(() => {
        void loadSummaries();
        const interval = setInterval(() => void loadSummaries(), 10000);

        return () => clearInterval(interval);
    }, [loadSummaries]);

    const activateAccount = (nsid: string) => {
        router.post('/flickr/activate', { connection_key: nsid }, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Flickr Accounts" />

            <div className="space-y-6">
                <PageHeading
                    breadcrumbs={<Breadcrumbs items={[flickrRootCrumb()]} />}
                    title="Flickr Accounts"
                    subtitle="Run crawls and transfers for connected accounts."
                />

                <DataTable
                    columns={[
                        {
                            key: 'account',
                            label: 'Account',
                            sortable: true,
                            render: (account) => (
                                <div>
                                    <div className="font-medium text-slate-900">
                                        {account.fullname || account.username || account.nsid}
                                    </div>
                                    <div className="text-xs text-slate-500">@{account.username ?? account.nsid}</div>
                                    <div className="mt-2 flex gap-3 text-xs">
                                        <Link
                                            href={flickrAccountPath(account.public_id, '/contacts')}
                                            className="text-cyan-700 hover:underline"
                                        >
                                            Contacts
                                        </Link>
                                        <Link
                                            href={flickrAccountPath(account.public_id, '/photos')}
                                            className="text-cyan-700 hover:underline"
                                        >
                                            Photos
                                        </Link>
                                    </div>
                                </div>
                            ),
                        },
                        {
                            key: 'status',
                            label: 'Status',
                            sortable: true,
                            render: (account) =>
                                account.is_active ? (
                                    <span className="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                        Active
                                    </span>
                                ) : (
                                    <Button variant="link" size="xs" className="px-0" onClick={() => activateAccount(account.nsid)}>
                                        Set active
                                    </Button>
                                ),
                        },
                        {
                            key: 'quota',
                            label: 'API quota',
                            sortable: true,
                            render: (account) => {
                                const summary = summaries[account.nsid];

                                return summary?.rate_limit ? (
                                    <div className="max-w-xs space-y-1">
                                        <RateLimitMeter rateLimit={summary.rate_limit} />
                                        <p className="text-xs text-slate-500">
                                            Running: {summary.runs.running} · Pending: {summary.pending_targets}
                                        </p>
                                    </div>
                                ) : (
                                    <span className="text-xs text-slate-400">—</span>
                                );
                            },
                        },
                    ]}
                    data={sortedAccounts}
                    rowKey={(account) => account.public_id}
                    sortKey={sortKey}
                    sortDirection={sortDirection}
                    onSortChange={handleSortChange}
                    emptyMessage={
                        <>
                            No Flickr accounts connected yet.{' '}
                            <Link href="/settings?tab=flickr" className="text-cyan-700 hover:underline">
                                Connect in Settings
                            </Link>
                        </>
                    }
                    actionsColumn={(account) => (
                        <CrawlActionBar scope="account" accountPublicId={account.public_id} />
                    )}
                />
            </div>
        </AppLayout>
    );
}
