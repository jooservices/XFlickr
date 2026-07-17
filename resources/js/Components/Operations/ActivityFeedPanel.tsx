import { useMemo, useState } from 'react';

import ActivityDetailDrawer from '@/Components/Operations/ActivityDetailDrawer';
import ActivityFeedRow from '@/Components/Operations/ActivityFeedRow';
import Button from '@/Components/ui/Button';
import EmptyState from '@/Components/ui/EmptyState';
import LoadingIndicator from '@/Components/ui/LoadingIndicator';
import MetricCard from '@/Components/ui/MetricCard';
import {
    applyActivityFeedRange,
    useActivityFeed,
    useActivityFeedFilterState,
    writeActivityFeedFiltersToLocation,
} from '@/hooks/useActivityFeed';
import type { ActivityFeedEntry, ActivityFeedFilters, ActivityFeedRange } from '@/lib/activityFeed';
import { cn } from '@/lib/cn';
import { formatCount } from '@/lib/format';

const TYPE_OPTIONS = [
    { value: '', label: 'All' },
    { value: 'domain', label: 'Domain' },
    { value: 'audit', label: 'Audit' },
    { value: 'system', label: 'System' },
    { value: 'security', label: 'Security' },
] as const;

const LEVEL_OPTIONS = [
    { value: '', label: 'All' },
    { value: 'info', label: 'Info' },
    { value: 'warning', label: 'Warning' },
    { value: 'error', label: 'Error' },
] as const;

const RANGE_OPTIONS: { value: ActivityFeedRange; label: string }[] = [
    { value: '24h', label: 'Last 24h' },
    { value: '7d', label: 'Last 7d' },
    { value: '30d', label: 'Last 30d' },
    { value: 'all', label: 'All time' },
];

function ChipGroup<T extends string>({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: readonly { value: T | ''; label: string }[];
    onChange: (next: T | '') => void;
}) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <span className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</span>
            {options.map((option) => {
                const active = value === option.value;

                return (
                    <button
                        key={option.value || 'all'}
                        type="button"
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'rounded-full px-2.5 py-1 text-xs font-medium transition-colors',
                            active
                                ? 'bg-cyan-100 text-cyan-800'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}

interface ActivityFeedPanelProps {
    filters: ActivityFeedFilters;
    onFiltersChange: (next: ActivityFeedFilters) => void;
}

export default function ActivityFeedPanel({ filters, onFiltersChange }: ActivityFeedPanelProps) {
    const { entries, meta, loading, error, refresh, range } = useActivityFeed(filters);
    const { update } = useActivityFeedFilterState(filters, onFiltersChange);
    const [selected, setSelected] = useState<ActivityFeedEntry | null>(null);

    const byLevel = meta?.facets.by_level ?? {};
    const total = meta?.total ?? 0;
    const infoCount = (byLevel.info ?? 0) + (byLevel.notice ?? 0) + (byLevel.debug ?? 0);
    const warningCount = byLevel.warning ?? 0;
    const errorCount =
        (byLevel.error ?? 0) + (byLevel.critical ?? 0) + (byLevel.alert ?? 0) + (byLevel.emergency ?? 0);

    const pageLabel = useMemo(() => {
        if (!meta) {
            return null;
        }

        return `Page ${meta.current_page} of ${Math.max(meta.last_page, 1)}`;
    }, [meta]);

    return (
        <div className="space-y-6" data-testid="activity-feed-panel">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <MetricCard label="Total" value={total} tone="slate" />
                <MetricCard label="Info" value={infoCount} tone="emerald" />
                <MetricCard label="Warning" value={warningCount} tone="amber" />
                <MetricCard label="Error" value={errorCount} tone="rose" />
            </div>

            <div className="space-y-3 rounded-md border border-slate-200 bg-white p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <ChipGroup
                        label="Type"
                        value={filters.type}
                        options={TYPE_OPTIONS}
                        onChange={(type) => update({ type })}
                    />
                    <Button variant="secondary" size="sm" onClick={refresh}>
                        Refresh
                    </Button>
                </div>

                <ChipGroup
                    label="Level"
                    value={filters.level}
                    options={LEVEL_OPTIONS}
                    onChange={(level) => update({ level })}
                />

                <div className="flex flex-wrap items-end gap-3">
                    <label className="block text-sm">
                        <span className="text-xs font-medium uppercase tracking-wide text-slate-500">Action prefix</span>
                        <input
                            value={filters.action_prefix}
                            onChange={(event) => update({ action_prefix: event.target.value })}
                            placeholder="crawler."
                            className="mt-1 block w-52 rounded-md border border-slate-200 px-2.5 py-1.5 text-sm text-slate-800"
                        />
                    </label>
                    <label className="block text-sm">
                        <span className="text-xs font-medium uppercase tracking-wide text-slate-500">Correlation</span>
                        <input
                            value={filters.correlation_id}
                            onChange={(event) => update({ correlation_id: event.target.value })}
                            placeholder="run id"
                            className="mt-1 block w-40 rounded-md border border-slate-200 px-2.5 py-1.5 font-mono text-sm text-slate-800"
                        />
                    </label>
                    {filters.correlation_id ? (
                        <Button variant="ghost" size="sm" onClick={() => update({ correlation_id: '' })}>
                            Clear
                        </Button>
                    ) : null}
                    <label className="block text-sm">
                        <span className="text-xs font-medium uppercase tracking-wide text-slate-500">Range</span>
                        <select
                            value={range}
                            onChange={(event) => {
                                const next = applyActivityFeedRange(
                                    event.target.value as ActivityFeedRange,
                                    filters,
                                );
                                writeActivityFeedFiltersToLocation(next);
                                onFiltersChange(next);
                            }}
                            className="mt-1 block rounded-md border border-slate-200 px-2.5 py-1.5 text-sm text-slate-800"
                        >
                            {RANGE_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>
            </div>

            {error !== null ? (
                <div className="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                    Unable to load activity feed. Try again.
                </div>
            ) : null}

            <div className="overflow-hidden rounded-md border border-slate-200 bg-white">
                {loading && entries.length === 0 ? (
                    <div className="px-4 py-8">
                        <LoadingIndicator label="Loading activity…" />
                    </div>
                ) : entries.length === 0 ? (
                    <div className="px-4 py-8">
                        <EmptyState
                            title="No activity yet"
                            description="Domain and audit records from crawler and settings will appear here."
                        />
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {entries.map((entry) => (
                            <li key={entry.id}>
                                <ActivityFeedRow
                                    entry={entry}
                                    selected={selected?.id === entry.id}
                                    onSelect={setSelected}
                                />
                            </li>
                        ))}
                    </ul>
                )}

                {meta !== null && meta.last_page > 0 ? (
                    <div className="flex items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-600">
                        <p>
                            {pageLabel}
                            {meta.total > 0 ? ` · ${formatCount(meta.total)} total` : null}
                        </p>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="secondary"
                                size="sm"
                                disabled={meta.current_page <= 1}
                                onClick={() => update({ page: meta.current_page - 1 })}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="secondary"
                                size="sm"
                                disabled={meta.current_page >= meta.last_page}
                                onClick={() => update({ page: meta.current_page + 1 })}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                ) : null}
            </div>

            <ActivityDetailDrawer
                entry={selected}
                onClose={() => setSelected(null)}
                onFilterCorrelation={(correlationId) => update({ correlation_id: correlationId, page: 1 })}
            />
        </div>
    );
}
