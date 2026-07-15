import { useMemo } from 'react';

import ContactNsidLinks from '@/Components/Contacts/NsidLinks';
import DataTable from '@/Components/ui/DataTable';
import PageSection from '@/Components/ui/PageSection';
import ProgressBar from '@/Components/ui/ProgressBar';
import StatusBadge from '@/Components/ui/StatusBadge';
import { useTableSort } from '@/hooks/useTableSort';
import { accountLabel, fetchRunSortValue } from '@/lib/crawlOperations';
import { formatRelativeTime } from '@/lib/format';
import { sortClientData } from '@/lib/tableSort';
import type {
    CrawlRun,
    FlickrAccount,
    OperationsAccountRow,
    OperationsSpiderRow,
    OperationsTargetBreakdownRow,
} from '@/types';

interface OperationsCrawlPanelProps {
    fetchRuns: CrawlRun[];
    accounts: FlickrAccount[];
    opsAccounts: OperationsAccountRow[];
    targetBreakdown: OperationsTargetBreakdownRow[];
    spider: OperationsSpiderRow[];
    loading: boolean;
}

export default function OperationsCrawlPanel({
    fetchRuns,
    accounts,
    opsAccounts,
    targetBreakdown,
    spider,
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

    const activeSpider = useMemo(() => spider.filter((row) => row.status.active && row.status.run), [spider]);

    const breakdownByRun = useMemo(() => {
        const map = new Map<number, OperationsTargetBreakdownRow[]>();
        for (const row of targetBreakdown) {
            const list = map.get(row.crawl_run_id) ?? [];
            list.push(row);
            map.set(row.crawl_run_id, list);
        }

        return map;
    }, [targetBreakdown]);

    return (
        <div className="space-y-8">
            {activeSpider.length > 0 ? (
                <PageSection
                    title="Spider frontier"
                    description="Read-only frontier progress for active auto-expand runs. Start/stop lives on Flickr Expand actions."
                >
                    <ul className="space-y-3 text-sm">
                        {activeSpider.map((row) => {
                            const run = row.status.run;
                            if (! run) {
                                return null;
                            }

                            return (
                                <li
                                    key={row.connection_key}
                                    className="rounded-md border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900"
                                >
                                    <p className="font-medium text-slate-900 dark:text-slate-100">{row.label}</p>
                                    <p className="mt-1 text-xs text-slate-500">
                                        depth ≤ {run.max_depth} · pending {run.pending} · queued {run.queued} ·
                                        crawled {run.crawled} · discovered {run.contacts_discovered}
                                    </p>
                                    <ProgressBar
                                        className="mt-2"
                                        value={run.crawled}
                                        max={Math.max(run.crawled + run.pending + run.queued, 1)}
                                        showLabel={false}
                                    />
                                </li>
                            );
                        })}
                    </ul>
                </PageSection>
            ) : null}

            {targetBreakdown.length > 0 ? (
                <PageSection
                    title="Active target breakdown"
                    description="Crawl target counts by status and task type for running fetch runs."
                >
                    <ul className="space-y-3 text-sm text-slate-700 dark:text-slate-300">
                        {[...breakdownByRun.entries()].map(([runId, rows]) => (
                            <li key={runId}>
                                <p className="font-medium text-slate-900 dark:text-slate-100">Run #{runId}</p>
                                <ul className="mt-1 space-y-0.5 text-xs text-slate-500">
                                    {rows.map((row) => (
                                        <li key={`${row.crawl_run_id}-${row.status}-${row.task_type}`}>
                                            {row.task_type} · {row.status} · {row.count}
                                        </li>
                                    ))}
                                </ul>
                            </li>
                        ))}
                    </ul>
                </PageSection>
            ) : null}

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
