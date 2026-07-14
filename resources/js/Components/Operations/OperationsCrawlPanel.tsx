import { useMemo } from 'react';

import ContactNsidLinks from '@/Components/ContactNsidLinks';
import DataTable from '@/Components/DataTable';
import PageSection from '@/Components/PageSection';
import ProgressBar from '@/Components/ProgressBar';
import StatusBadge from '@/Components/StatusBadge';
import { useTableSort } from '@/hooks/useTableSort';
import { accountLabel, fetchRunSortValue } from '@/lib/crawlOperations';
import { formatRelativeTime } from '@/lib/format';
import { sortClientData } from '@/lib/tableSort';
import type { CrawlRun, FlickrAccount, OperationsAccountRow } from '@/types';

interface OperationsCrawlPanelProps {
    fetchRuns: CrawlRun[];
    accounts: FlickrAccount[];
    opsAccounts: OperationsAccountRow[];
    loading: boolean;
}

export default function OperationsCrawlPanel({
    fetchRuns,
    accounts,
    opsAccounts,
    loading,
}: OperationsCrawlPanelProps) {
    const fetchSort = useTableSort({ initialSort: 'id', initialDirection: 'desc' });

    const accountByNsid = useMemo(
        () => Object.fromEntries(accounts.map((account) => [account.nsid, account])),
        [accounts],
    );

    const sortedFetchRuns = useMemo(
        () =>
            sortClientData(fetchRuns, fetchSort.sortKey, fetchSort.sortDirection, (run, key) =>
                fetchRunSortValue(run, key, accountByNsid),
            ),
        [fetchRuns, fetchSort.sortKey, fetchSort.sortDirection, accountByNsid],
    );

    return (
        <div className="space-y-8">
            <PageSection
                title="Fetch"
                description="Flickr API crawls discovering contacts, photos, photosets, galleries, and favorites. Includes recent completed and failed runs."
            >
                <DataTable
                    busy={loading}
                    busyLabel="Loading fetch runs…"
                    columns={[
                        {
                            key: 'id',
                            label: 'Run',
                            sortable: true,
                            render: (run) => <span className="font-mono text-xs">#{run.id}</span>,
                        },
                        {
                            key: 'account',
                            label: 'Account',
                            sortable: true,
                            render: (run) => accountLabel(accountByNsid[run.connection_key]),
                        },
                        {
                            key: 'crawl_type',
                            label: 'Type',
                            sortable: true,
                            render: (run) => <span className="capitalize">{run.crawl_type}</span>,
                        },
                        {
                            key: 'subject',
                            label: 'Subject',
                            sortable: true,
                            render: (run) => {
                                const nsid = run.subject_nsid ?? run.connection_key;

                                return (
                                    <ContactNsidLinks
                                        nsid={nsid}
                                        accountPublicId={accountByNsid[run.connection_key]?.public_id}
                                    />
                                );
                            },
                        },
                        {
                            key: 'progress',
                            label: 'Progress',
                            sortable: true,
                            className: 'w-52',
                            render: (run) => {
                                const discovered = run.photos_discovered + run.contacts_discovered;
                                const max = Math.max(discovered, run.api_calls, 1);

                                return (
                                    <div>
                                        <ProgressBar value={discovered} max={max} showLabel={false} />
                                        <p className="mt-1 text-xs text-slate-500">
                                            {run.photos_discovered} photos · {run.contacts_discovered} contacts ·{' '}
                                            {run.api_calls} API
                                        </p>
                                        {run.failed_reason ? (
                                            <p
                                                className="mt-1 line-clamp-2 text-xs text-red-600"
                                                title={run.failed_reason}
                                            >
                                                {run.failed_reason}
                                            </p>
                                        ) : null}
                                    </div>
                                );
                            },
                        },
                        {
                            key: 'started_at',
                            label: 'Started',
                            sortable: true,
                            render: (run) => (
                                <span className="text-xs text-slate-500">{formatRelativeTime(run.started_at)}</span>
                            ),
                        },
                        {
                            key: 'status',
                            label: 'Status',
                            sortable: true,
                            render: (run) => <StatusBadge status={run.status} />,
                        },
                    ]}
                    data={sortedFetchRuns}
                    rowKey={(run) => String(run.id)}
                    sortKey={fetchSort.sortKey}
                    sortDirection={fetchSort.sortDirection}
                    onSortChange={fetchSort.handleSortChange}
                    emptyMessage="No recent fetch runs."
                />
            </PageSection>

            {opsAccounts.length > 0 ? (
                <PageSection title="Pending targets" description="Crawl targets waiting for dispatch per account.">
                    <ul className="space-y-2 text-sm text-slate-700 dark:text-slate-300">
                        {opsAccounts.map((row) => (
                            <li key={row.connection_key}>
                                <span className="font-medium text-slate-900 dark:text-slate-100">{row.label}</span>
                                {' · '}
                                {row.pending_targets} pending
                                {row.rate_limit.cooldown_seconds_remaining > 0
                                    ? ` · cooldown ${Math.ceil(row.rate_limit.cooldown_seconds_remaining / 60)}m`
                                    : ''}
                            </li>
                        ))}
                    </ul>
                </PageSection>
            ) : null}
        </div>
    );
}
