import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

import ApiUsageChart from '@/Components/Flickr/ApiUsageChart';
import RateLimitMeter from '@/Components/Flickr/RateLimitMeter';
import { PageShell, PageShellCanvas, PageShellIdentity } from '@/Components/Layout/page-shell';
import DatabaseUsagePanel from '@/Components/Operations/DatabaseUsagePanel';
import OnboardingWizard from '@/Components/Settings/OnboardingWizard';
import Card from '@/Components/ui/Card';
import MetricCard from '@/Components/ui/MetricCard';
import { usePolledResource } from '@/hooks/usePolledResource';
import { useStorageQuota } from '@/hooks/useStorageQuota';
import AppLayout from '@/Layouts/AppLayout';
import { flickrAccountPath } from '@/lib/flickrAccount';
import { resolveDefaultFlickrQuotaNsid } from '@/lib/flickrQuotaAccount';
import { dashboardHasCompletedCrawl } from '@/lib/onboardingProgress';
import type { DashboardSnapshot, FlickrAccount, PageProps } from '@/types';

interface Props extends PageProps {
    snapshot: DashboardSnapshot;
}

function accountLabel(account: FlickrAccount): string {
    return account.fullname || account.username || account.nsid;
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat().format(value);
}

const emptyDatabases: DashboardSnapshot['databases'] = {
    mysql: {
        status: 'error',
        driver: 'unknown',
        database: null,
        size_bytes: null,
        connections_current: null,
        connections_max: null,
        tables: [],
        error: null,
    },
    mongodb: {
        status: 'error',
        driver: 'mongodb',
        database: null,
        size_bytes: null,
        collections: null,
        objects: null,
        error: null,
    },
    history: [],
};

export default function Dashboard() {
    const { snapshot: initialSnapshot } = usePage<Props>().props;
    const { data } = usePolledResource<{ data: DashboardSnapshot }>('/api/v1/dashboard/snapshot', {
        intervalMs: 15_000,
    });
    const snapshot = data?.data ?? initialSnapshot;

    const rows = snapshot.accounts;
    const global = snapshot.global;
    const databases = snapshot.databases ?? emptyDatabases;
    const anyCooldown = snapshot.alerts.any_cooldown;
    const databaseUnreachable = Boolean(snapshot.alerts.database_unreachable);
    const mysqlConnectionsHigh = Boolean(snapshot.alerts.mysql_connections_high);
    const accountList = useMemo(() => rows.map((row) => row.account), [rows]);
    const { snapshot: storageQuotaSnapshot } = useStorageQuota();
    const hasStorageAccounts = (storageQuotaSnapshot?.accounts?.length ?? 0) > 0;
    const hasCompletedCrawl = useMemo(() => dashboardHasCompletedCrawl(rows), [rows]);

    const [selectedNsid, setSelectedNsid] = useState<string | null>(() =>
        resolveDefaultFlickrQuotaNsid(accountList),
    );

    useEffect(() => {
        setSelectedNsid((current) => {
            if (current && accountList.some((account) => account.nsid === current)) {
                return current;
            }

            return resolveDefaultFlickrQuotaNsid(accountList);
        });
    }, [accountList]);

    const selectedAccountRow = useMemo(
        () => rows.find((row) => row.account.nsid === selectedNsid) ?? null,
        [rows, selectedNsid],
    );

    const contactsCount = selectedAccountRow?.contacts_db ?? 0;
    const photosCount = selectedAccountRow?.photos_db ?? 0;
    const photosWithSizesCount = selectedAccountRow?.photos_with_sizes ?? 0;
    const photosetsCount = selectedAccountRow?.photosets_db ?? 0;
    const galleriesCount = selectedAccountRow?.galleries_db ?? 0;

    const catalogAccountHint = useMemo(() => {
        if (accountList.length === 0) {
            return 'No account connected';
        }

        if (accountList.length > 1 && selectedAccountRow) {
            return accountLabel(selectedAccountRow.account);
        }

        return 'Linked to this account';
    }, [accountList.length, selectedAccountRow]);

    const hasAlerts = useMemo(
        () =>
            anyCooldown ||
            global.failed_transfers_24h > 0 ||
            databaseUnreachable ||
            mysqlConnectionsHigh,
        [anyCooldown, global.failed_transfers_24h, databaseUnreachable, mysqlConnectionsHigh],
    );

    return (
        <AppLayout>
            <Head title="Dashboard" />

            <PageShell>
                <PageShellIdentity
                    breadcrumbs={[{ label: 'Dashboard' }]}
                    title="Dashboard"
                    subtitle="Monitor crawl progress, catalog growth, transfer health, and database usage."
                />

                <PageShellCanvas className="space-y-6" variant="plain">
                    <OnboardingWizard
                        hasFlickrAccounts={accountList.length > 0}
                        hasStorageAccounts={hasStorageAccounts}
                        hasCompletedCrawl={hasCompletedCrawl}
                    />
                    <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <MetricCard label="Accounts" value={formatNumber(global.accounts)} tone="slate" />
                        <MetricCard
                            label="Active crawls"
                            value={formatNumber(global.runs_running)}
                            hint={`${formatNumber(global.pending_targets)} pending target(s)`}
                            tone={global.runs_running > 0 ? 'cyan' : 'slate'}
                        />
                        <MetricCard
                            label="Transfers"
                            value={`${formatNumber(global.downloads_active + global.uploads_active)} active`}
                            hint={`${formatNumber(global.failed_transfers_24h)} failed in 24h`}
                            tone={global.failed_transfers_24h > 0 ? 'rose' : 'slate'}
                        />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <MetricCard
                            label="Contacts"
                            value={formatNumber(contactsCount)}
                            hint={catalogAccountHint}
                            tone="emerald"
                        />
                        <MetricCard
                            label="Photos"
                            value={formatNumber(photosCount)}
                            hint={`${formatNumber(photosWithSizesCount)} with sizes`}
                            tone="violet"
                        />
                        <MetricCard
                            label="Photosets"
                            value={formatNumber(photosetsCount)}
                            hint={catalogAccountHint}
                            tone="cyan"
                        />
                        <MetricCard
                            label="Galleries"
                            value={formatNumber(galleriesCount)}
                            hint={catalogAccountHint}
                            tone="amber"
                        />
                    </div>
                </div>

                <DatabaseUsagePanel databases={databases} />

                <ApiUsageChart accounts={rows.map((row) => row.account)} />

                {hasAlerts ? (
                    <Card title="Alerts" subtitle="Items that may need attention." showFooter={false}>
                        <div className="space-y-2 text-sm">
                            {anyCooldown ? (
                                <div className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-rose-900">
                                    One or more accounts are in API cooldown.
                                </div>
                            ) : null}
                            {global.failed_transfers_24h > 0 ? (
                                <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-900">
                                    {formatNumber(global.failed_transfers_24h)} transfer item(s) failed in the last 24 hours. Check Operations.
                                </div>
                            ) : null}
                            {databaseUnreachable ? (
                                <div className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-rose-900">
                                    MySQL or MongoDB is unreachable. Check database connectivity.
                                </div>
                            ) : null}
                            {mysqlConnectionsHigh ? (
                                <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-900">
                                    MySQL connections are at or above 80% of max_connections.
                                </div>
                            ) : null}
                        </div>
                    </Card>
                ) : null}

                <div className="space-y-3">
                    <h2 className="text-lg font-medium text-slate-900">Accounts</h2>
                    {rows.length === 0 ? (
                        <Card
                            title="No connected Flickr accounts"
                            subtitle="Connect an account first, then stats and operations will show up here."
                            showFooter={false}
                        />
                    ) : (
                        <div className="grid gap-4 lg:grid-cols-2">
                            {rows.map((row) => {
                                const account = row.account;
                                const latest = row.latest_run;
                                const latestLabel = latest ? `#${latest.id} · ${latest.crawl_type} · ${latest.status}` : '—';

                                return (
                                    <Card
                                        key={account.public_id}
                                        title={accountLabel(account)}
                                        subtitle={account.nsid}
                                        headerActions={
                                            <Link
                                                href={flickrAccountPath(account.public_id, '/contacts')}
                                                className="text-sm font-medium text-cyan-700 hover:underline"
                                            >
                                                View contacts
                                            </Link>
                                        }
                                        footer={
                                            <Link href="/operations" className="text-sm font-medium text-cyan-700 hover:underline">
                                                View operations
                                            </Link>
                                        }
                                    >
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-3">
                                                <div className="rounded-md border border-slate-200 bg-slate-50 p-3">
                                                    <p className="text-xs font-medium text-slate-600">Crawl runs</p>
                                                    <p className="mt-1 text-sm text-slate-700">
                                                        <span className="font-semibold text-slate-900">{row.runs.running}</span> running ·{' '}
                                                        <span className="font-semibold text-slate-900">{row.runs.failed}</span> failed
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-500">
                                                        Pending targets: {formatNumber(row.pending_targets)}
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-500">Latest: {latestLabel}</p>
                                                </div>

                                                <div className="rounded-md border border-slate-200 bg-slate-50 p-3">
                                                    <p className="text-xs font-medium text-slate-600">Transfers</p>
                                                    <p className="mt-1 text-sm text-slate-700">
                                                        <span className="font-semibold text-slate-900">{row.transfers.downloads_active}</span> downloads ·{' '}
                                                        <span className="font-semibold text-slate-900">{row.transfers.uploads_active}</span> uploads
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-500">
                                                        Failed 24h: {formatNumber(row.transfers.failed_items_24h)}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="rounded-md border border-slate-200 bg-white p-3">
                                                <RateLimitMeter rateLimit={row.rate_limit} />
                                            </div>
                                        </div>
                                    </Card>
                                );
                            })}
                        </div>
                    )}
                </div>
                </PageShellCanvas>
            </PageShell>
        </AppLayout>
    );
}
