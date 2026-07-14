import DatabaseUsagePanel from '@/Components/Operations/DatabaseUsagePanel';
import OperationsActivityChart from '@/Components/Operations/OperationsActivityChart';
import MetricCard from '@/Components/ui/MetricCard';
import type {
    DatabaseUsageSnapshot,
    OperationsAccountRow,
    OperationsActivityPoint,
    OperationsOverviewTotals,
    ServiceDependencyProbe,
} from '@/types';

type Dependencies = {
    mysql: ServiceDependencyProbe;
    redis: ServiceDependencyProbe;
    mongodb: ServiceDependencyProbe;
};

interface OperationsOverviewPanelProps {
    overview: OperationsOverviewTotals;
    dependencies: Dependencies;
    databases: DatabaseUsageSnapshot | null;
    accounts: OperationsAccountRow[];
    activityHistory: OperationsActivityPoint[];
}

function probeHint(probe: ServiceDependencyProbe): string {
    return [probe.latency_ms !== null ? `${probe.latency_ms} ms` : null, probe.detail]
        .filter(Boolean)
        .join(' · ') || 'Dependency probe';
}

export default function OperationsOverviewPanel({
    overview,
    dependencies,
    databases,
    accounts,
    activityHistory,
}: OperationsOverviewPanelProps) {
    const transfersActive = overview.downloads_active + overview.uploads_active;

    return (
        <div className="space-y-6">
            <div className="space-y-2">
                <p className="text-xs font-medium uppercase tracking-wide text-slate-500">Activity</p>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <MetricCard
                        label="Processing"
                        value={overview.runs_running}
                        hint="Active crawl runs"
                        tone={overview.runs_running > 0 ? 'cyan' : 'slate'}
                    />
                    <MetricCard
                        label="Pending"
                        value={overview.pending_targets}
                        hint="Targets waiting for dispatch"
                        tone={overview.pending_targets > 0 ? 'amber' : 'slate'}
                    />
                    <MetricCard
                        label="Transfers"
                        value={transfersActive}
                        hint={`${overview.downloads_active} download · ${overview.uploads_active} upload`}
                        tone={transfersActive > 0 ? 'cyan' : 'slate'}
                    />
                    <MetricCard
                        label="Failed 24h"
                        value={overview.failed_transfers_24h}
                        hint="Failed transfer items"
                        tone={overview.failed_transfers_24h > 0 ? 'rose' : 'slate'}
                    />
                    <MetricCard
                        label="Cooldown"
                        value={overview.accounts_in_cooldown}
                        hint="Accounts in rate-limit cooldown"
                        tone={overview.accounts_in_cooldown > 0 ? 'amber' : 'slate'}
                    />
                </div>
            </div>

            <OperationsActivityChart history={activityHistory} />

            <div className="space-y-2">
                <p className="text-xs font-medium uppercase tracking-wide text-slate-500">Services</p>
                <div className="grid gap-4 sm:grid-cols-3">
                    {(
                        [
                            ['mysql', 'MySQL'],
                            ['redis', 'Redis'],
                            ['mongodb', 'MongoDB'],
                        ] as const
                    ).map(([key, label]) => {
                        const probe = dependencies[key];

                        return (
                            <MetricCard
                                key={key}
                                label={label}
                                value={probe.ok ? 'ok' : 'down'}
                                hint={probeHint(probe)}
                                tone={probe.ok ? 'slate' : 'rose'}
                            />
                        );
                    })}
                </div>
            </div>

            {databases ? (
                <DatabaseUsagePanel
                    databases={databases}
                    subtitle="MySQL catalog/app store and MongoDB config store. Size samples accumulate while Operations is open."
                />
            ) : null}

            {accounts.length > 0 ? (
                <div className="space-y-2">
                    <p className="text-xs font-medium uppercase tracking-wide text-slate-500">Accounts</p>
                    <ul className="divide-y divide-slate-200 rounded-md border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900">
                        {accounts.map((row) => {
                            const used = row.rate_limit.requests_used;
                            const max = row.rate_limit.max_requests_per_hour;
                            const pct = max > 0 ? Math.min(100, Math.round((used / max) * 100)) : 0;
                            const cooldown = row.rate_limit.cooldown_seconds_remaining;

                            return (
                                <li
                                    key={row.connection_key}
                                    className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm"
                                >
                                    <div>
                                        <p className="font-medium text-slate-900 dark:text-slate-100">{row.label}</p>
                                        <p className="text-xs text-slate-500">
                                            pending {row.pending_targets}
                                            {cooldown > 0 ? ` · cooldown ${Math.ceil(cooldown / 60)}m` : ''}
                                        </p>
                                    </div>
                                    <div className="min-w-40 text-right">
                                        <p className="text-xs text-slate-500">
                                            quota {used}/{max}
                                        </p>
                                        <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                            <div
                                                className="h-full rounded-full bg-slate-700 dark:bg-slate-300"
                                                style={{ width: `${pct}%` }}
                                            />
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            ) : null}
        </div>
    );
}
