import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, Camera, Images, Layers, Settings, Workflow } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import Breadcrumbs from '@/Components/Breadcrumbs';
import Card from '@/Components/Card';
import PageHeading from '@/Components/PageHeading';
import RateLimitMeter from '@/Components/RateLimitMeter';
import StatCard from '@/Components/StatCard';
import AppLayout from '@/Layouts/AppLayout';
import { apiGet } from '@/lib/apiClient';
import { flickrAccountPath } from '@/lib/flickrAccount';
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

export default function Dashboard() {
    const { snapshot: initialSnapshot } = usePage<Props>().props;
    const [snapshot, setSnapshot] = useState(initialSnapshot);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setSnapshot(initialSnapshot);
    }, [initialSnapshot]);

    useEffect(() => {
        const controller = new AbortController();

        const poll = () => {
            setLoading(true);
            void apiGet<{ data: DashboardSnapshot }>('/api/dashboard/snapshot', { signal: controller.signal })
                .then((json) => setSnapshot(json.data))
                .catch(() => undefined)
                .finally(() => setLoading(false));
        };

        poll();
        const interval = setInterval(poll, 5000);

        return () => {
            controller.abort();
            clearInterval(interval);
        };
    }, []);

    const rows = snapshot.accounts;
    const global = snapshot.global;
    const anyCooldown = snapshot.alerts.any_cooldown;

    const hasAlerts = useMemo(
        () => anyCooldown || global.failed_transfers_24h > 0,
        [anyCooldown, global.failed_transfers_24h],
    );

    return (
        <AppLayout>
            <Head title="Dashboard" />

            <div className="space-y-6">
                <PageHeading
                    breadcrumbs={<Breadcrumbs items={[{ label: 'Dashboard' }]} />}
                    title="Dashboard"
                    subtitle="Monitor crawl progress, catalog growth, and transfer health."
                />

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="text-xs text-slate-500">
                        {loading ? (
                            'Refreshing…'
                        ) : (
                            <>
                                Updated <span className="font-medium">{new Date(snapshot.generated_at).toLocaleTimeString()}</span>
                            </>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href="/flickr/accounts"
                            className="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            <Camera className="h-4 w-4" />
                            Flickr Accounts
                        </Link>
                        <Link
                            href="/crawl/operations"
                            className="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            <Workflow className="h-4 w-4" />
                            Operations
                        </Link>
                        <Link
                            href="/settings"
                            className="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            <Settings className="h-4 w-4" />
                            Settings
                        </Link>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Accounts" value={formatNumber(global.accounts)} icon={<Camera className="h-4 w-4" />} tone="slate" />
                    <StatCard
                        label="Active crawls"
                        value={formatNumber(global.runs_running)}
                        hint={`${formatNumber(global.pending_targets)} pending target(s)`}
                        icon={<Activity className="h-4 w-4" />}
                        tone={global.runs_running > 0 ? 'cyan' : 'slate'}
                    />
                    <StatCard
                        label="Photos (DB)"
                        value={formatNumber(global.photos_db)}
                        hint={`${formatNumber(global.photos_with_sizes)} with sizes`}
                        icon={<Images className="h-4 w-4" />}
                        tone="violet"
                    />
                    <StatCard
                        label="Transfers"
                        value={`${formatNumber(global.downloads_active + global.uploads_active)} active`}
                        hint={`${formatNumber(global.failed_transfers_24h)} failed in 24h`}
                        icon={<Layers className="h-4 w-4" />}
                        tone={global.failed_transfers_24h > 0 ? 'rose' : 'slate'}
                    />
                </div>

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
                                            <Link href="/crawl/operations" className="text-sm font-medium text-cyan-700 hover:underline">
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
            </div>
        </AppLayout>
    );
}
