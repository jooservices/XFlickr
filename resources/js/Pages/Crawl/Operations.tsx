import { Head, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';

import Button from '@/Components/Button';
import ContactNsidLinks from '@/Components/ContactNsidLinks';
import DataTable from '@/Components/DataTable';
import { PageShell, PageShellCanvas, PageShellIdentity } from '@/Components/layout/page-shell';
import PageSection from '@/Components/PageSection';
import ProgressBar from '@/Components/ProgressBar';
import StatusBadge from '@/Components/StatusBadge';
import TransferBatchFailures from '@/Components/TransferBatchFailures';
import { useCrawlOperations } from '@/hooks/useCrawlOperations';
import { useTableSort } from '@/hooks/useTableSort';
import AppLayout from '@/Layouts/AppLayout';
import {
    accountLabel,
    downloadGroupLabel,
    downloadStoragePath,
    fetchRunSortValue,
    transferBatchSortValue,
} from '@/lib/crawlOperations';
import { flickrAccountPath } from '@/lib/flickrAccount';
import { sortClientData } from '@/lib/tableSort';
import type { FlickrAccount, PageProps } from '@/types';

interface Props extends PageProps {
    accounts: FlickrAccount[];
    spiderEnabled: boolean;
}

export default function CrawlOperations() {
    const { accounts, spiderEnabled } = usePage<Props>().props;
    const { fetchRuns, downloadBatches, uploadBatches, loading } = useCrawlOperations();

    const fetchSort = useTableSort({ initialSort: 'id', initialDirection: 'desc' });
    const downloadSort = useTableSort({ initialSort: 'id', initialDirection: 'desc' });
    const uploadSort = useTableSort({ initialSort: 'id', initialDirection: 'desc' });

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

    const sortedDownloadBatches = useMemo(
        () =>
            sortClientData(downloadBatches, downloadSort.sortKey, downloadSort.sortDirection, (batch, key) =>
                transferBatchSortValue(batch, key, accountByNsid, downloadGroupLabel),
            ),
        [downloadBatches, downloadSort.sortKey, downloadSort.sortDirection, accountByNsid],
    );

    const sortedUploadBatches = useMemo(
        () =>
            sortClientData(uploadBatches, uploadSort.sortKey, uploadSort.sortDirection, (batch, key) =>
                transferBatchSortValue(batch, key, accountByNsid, () => ''),
            ),
        [uploadBatches, uploadSort.sortKey, uploadSort.sortDirection, accountByNsid],
    );

    return (
        <AppLayout>
            <Head title="Operations" />

            <PageShell>
                <PageShellIdentity
                    breadcrumbs={[{ label: 'Operations' }]}
                    title="Operations"
                    subtitle="Active fetch, download, and upload jobs across connected accounts. Live updates via SSE (falls back to polling)."
                />

                <PageShellCanvas className="space-y-8" variant="plain">
                {spiderEnabled ? (
                    <PageSection
                        title="Spider"
                        description="Opt-in automatic contact expansion with depth and rate-limit caps. Enable spider.enabled in Settings → General runtime config."
                    >
                        <div className="flex flex-wrap gap-3">
                            {accounts.map((account) => (
                                <div
                                    key={account.nsid}
                                    className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900"
                                >
                                    <span className="text-sm font-medium text-slate-800 dark:text-slate-100">
                                        {accountLabel(account)}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="primary"
                                        onClick={() =>
                                            router.post(flickrAccountPath(account.public_id, '/spider/start'))
                                        }
                                    >
                                        Start
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() =>
                                            router.post(flickrAccountPath(account.public_id, '/spider/stop'))
                                        }
                                    >
                                        Stop
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </PageSection>
                ) : null}

                <PageSection
                    title="Fetch"
                    description="Flickr API crawls discovering contacts, photos, photosets, galleries, and favorites."
                >
                    {loading ? (
                        <p className="text-sm text-slate-500">Loading fetch runs…</p>
                    ) : (
                        <DataTable
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
                                    className: 'w-48',
                                    render: (run) => {
                                        const discovered = run.photos_discovered + run.contacts_discovered;
                                        const max = Math.max(discovered, run.api_calls, 1);

                                        return (
                                            <div>
                                                <ProgressBar value={discovered} max={max} showLabel={false} />
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {run.photos_discovered} photos · {run.api_calls} API calls
                                                </p>
                                            </div>
                                        );
                                    },
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
                            emptyMessage="No active fetch runs."
                        />
                    )}
                </PageSection>

                <PageSection
                    title="Download"
                    description="Photo downloads from Flickr into local storage, grouped by owner NSID and photoset or gallery when known."
                >
                    {loading ? (
                        <p className="text-sm text-slate-500">Loading download batches…</p>
                    ) : (
                        <DataTable
                            columns={[
                                {
                                    key: 'id',
                                    label: 'Batch',
                                    sortable: true,
                                    render: (batch) => <span className="font-mono text-xs">#{batch.id}</span>,
                                },
                                {
                                    key: 'account',
                                    label: 'Account',
                                    sortable: true,
                                    render: (batch) => accountLabel(accountByNsid[batch.connection_key]),
                                },
                                {
                                    key: 'owner',
                                    label: 'Owner',
                                    sortable: true,
                                    render: (batch) =>
                                        batch.subject_nsid ? (
                                            <ContactNsidLinks
                                                nsid={batch.subject_nsid}
                                                accountPublicId={accountByNsid[batch.connection_key]?.public_id}
                                            />
                                        ) : (
                                            '—'
                                        ),
                                },
                                {
                                    key: 'group',
                                    label: 'Group',
                                    sortable: true,
                                    render: (batch) => downloadGroupLabel(batch),
                                },
                                {
                                    key: 'storage',
                                    label: 'Storage',
                                    sortable: true,
                                    render: (batch) => (
                                        <span className="font-mono text-xs text-slate-500">
                                            {downloadStoragePath(batch)}
                                        </span>
                                    ),
                                },
                                {
                                    key: 'progress',
                                    label: 'Progress',
                                    sortable: true,
                                    className: 'w-48',
                                    render: (batch) => {
                                        const processed = batch.completed_count + batch.failed_count;
                                        const max = Math.max(batch.total_count, 1);

                                        return (
                                            <div>
                                                <ProgressBar value={processed} max={max} showLabel={false} />
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {batch.completed_count} completed
                                                    {batch.failed_count > 0 ? ` · ${batch.failed_count} failed` : ''}
                                                    {' · '}
                                                    {batch.total_count} total
                                                </p>
                                                {'sample_error' in batch && batch.sample_error ? (
                                                    <p
                                                        className="mt-1 line-clamp-2 text-xs text-red-600"
                                                        title={batch.sample_error}
                                                    >
                                                        {batch.sample_error}
                                                    </p>
                                                ) : null}
                                                <TransferBatchFailures
                                                    batch={batch}
                                                    account={accountByNsid[batch.connection_key]}
                                                />
                                            </div>
                                        );
                                    },
                                },
                                {
                                    key: 'status',
                                    label: 'Status',
                                    sortable: true,
                                    render: (batch) => <StatusBadge status={batch.status} />,
                                },
                            ]}
                            data={sortedDownloadBatches}
                            rowKey={(batch) => String(batch.id)}
                            sortKey={downloadSort.sortKey}
                            sortDirection={downloadSort.sortDirection}
                            onSortChange={downloadSort.handleSortChange}
                            emptyMessage={
                                <>
                                    No recent download batches. Downloads appear here when photos are queued and files
                                    land under storage/app/private/flickr/&lt;nsid&gt;/photos/.
                                </>
                            }
                        />
                    )}
                </PageSection>

                <PageSection
                    title="Upload"
                    description="Photo uploads from local storage to configured storage accounts."
                >
                    {loading ? (
                        <p className="text-sm text-slate-500">Loading upload batches…</p>
                    ) : (
                        <DataTable
                            columns={[
                                {
                                    key: 'id',
                                    label: 'Batch',
                                    sortable: true,
                                    render: (batch) => <span className="font-mono text-xs">#{batch.id}</span>,
                                },
                                {
                                    key: 'account',
                                    label: 'Account',
                                    sortable: true,
                                    render: (batch) => accountLabel(accountByNsid[batch.connection_key]),
                                },
                                {
                                    key: 'subject',
                                    label: 'Subject',
                                    sortable: true,
                                    render: (batch) =>
                                        batch.subject_nsid ? (
                                            <ContactNsidLinks
                                                nsid={batch.subject_nsid}
                                                accountPublicId={accountByNsid[batch.connection_key]?.public_id}
                                            />
                                        ) : (
                                            'All photos'
                                        ),
                                },
                                {
                                    key: 'progress',
                                    label: 'Progress',
                                    sortable: true,
                                    className: 'w-48',
                                    render: (batch) => {
                                        const processed = batch.completed_count + batch.failed_count;
                                        const max = Math.max(batch.total_count, 1);

                                        return (
                                            <div>
                                                <ProgressBar value={processed} max={max} showLabel={false} />
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {batch.completed_count} completed
                                                    {batch.failed_count > 0 ? ` · ${batch.failed_count} failed` : ''}
                                                    {' · '}
                                                    {batch.total_count} total
                                                </p>
                                                <TransferBatchFailures
                                                    batch={batch}
                                                    account={accountByNsid[batch.connection_key]}
                                                />
                                            </div>
                                        );
                                    },
                                },
                                {
                                    key: 'status',
                                    label: 'Status',
                                    sortable: true,
                                    render: (batch) => <StatusBadge status={batch.status} />,
                                },
                            ]}
                            data={sortedUploadBatches}
                            rowKey={(batch) => String(batch.id)}
                            sortKey={uploadSort.sortKey}
                            sortDirection={uploadSort.sortDirection}
                            onSortChange={uploadSort.handleSortChange}
                            emptyMessage="No active upload batches."
                        />
                    )}
                </PageSection>

                {accounts.length > 0 && (
                    <p className="text-xs text-slate-500">
                        Monitoring {accounts.length} account{accounts.length === 1 ? '' : 's'}.
                    </p>
                )}
                </PageShellCanvas>
            </PageShell>
        </AppLayout>
    );
}
