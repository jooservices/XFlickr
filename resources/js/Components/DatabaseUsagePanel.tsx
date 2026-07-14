import { useMemo, useState } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ProgressBar from '@/Components/ProgressBar';
import { cn } from '@/lib/cn';
import { formatBytes } from '@/lib/format';
import type { DatabaseUsageSnapshot } from '@/types';

type ChartMode = 'size' | 'connections';

interface DatabaseUsagePanelProps {
    databases: DatabaseUsageSnapshot;
    subtitle?: string;
}

function statusTone(status: 'ok' | 'error'): string {
    return status === 'ok' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800';
}

function formatTimeLabel(timestamp: number): string {
    return new Date(timestamp * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function connectionPercent(current: number | null, max: number | null): number | null {
    if (current === null || max === null || max <= 0) {
        return null;
    }

    return Math.min(100, Math.round((current / max) * 100));
}

export default function DatabaseUsagePanel({
    databases,
    subtitle = 'MySQL catalog/app store and MongoDB config store. Size samples accumulate while Dashboard is open.',
}: DatabaseUsagePanelProps) {
    const [chartMode, setChartMode] = useState<ChartMode>('size');
    const { mysql, mongodb, history } = databases;

    const connectionPct = connectionPercent(mysql.connections_current, mysql.connections_max);
    const connectionsHigh = connectionPct !== null && connectionPct >= 80;

    const chartRows = useMemo(() => {
        return history.map((point) => ({
            label: formatTimeLabel(point.t),
            mysql_size_mb:
                point.mysql_size_bytes === null ? null : Math.round((point.mysql_size_bytes / (1024 * 1024)) * 10) / 10,
            mongodb_size_mb:
                point.mongodb_size_bytes === null
                    ? null
                    : Math.round((point.mongodb_size_bytes / (1024 * 1024)) * 1000) / 1000,
            mysql_connections: point.mysql_connections,
        }));
    }, [history]);

    const showChart = chartRows.length >= 2;

    return (
        <Card
            title="Databases"
            subtitle={subtitle}
            showFooter={false}
        >
            <div className="space-y-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="rounded-md border border-slate-200 bg-slate-50 p-3">
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-sm font-medium text-slate-900">MySQL</p>
                            <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', statusTone(mysql.status))}>
                                {mysql.status === 'ok' ? 'ok' : 'error'}
                            </span>
                        </div>
                        <p className="mt-2 text-2xl font-semibold text-slate-900">{formatBytes(mysql.size_bytes)}</p>
                        <p className="mt-1 text-xs text-slate-500">
                            {mysql.database ?? '—'} · catalog + app
                        </p>

                        {mysql.connections_current !== null && mysql.connections_max !== null ? (
                            <div className="mt-3 space-y-1.5">
                                <div className="flex items-center justify-between text-xs">
                                    <span className="font-medium text-slate-700">Connections</span>
                                    <span className={cn('text-slate-500', connectionsHigh && 'text-amber-700')}>
                                        {mysql.connections_current} / {mysql.connections_max}
                                    </span>
                                </div>
                                <ProgressBar
                                    value={mysql.connections_current}
                                    max={mysql.connections_max}
                                    showLabel={false}
                                    className={cn(
                                        connectionsHigh && '[&>div>div]:bg-amber-500',
                                        connectionPct !== null && connectionPct >= 100 && '[&>div>div]:bg-red-500',
                                    )}
                                />
                            </div>
                        ) : (
                            <p className="mt-3 text-xs text-slate-500">Connection gauges unavailable for this driver.</p>
                        )}

                        {mysql.tables.length > 0 ? (
                            <ul className="mt-3 space-y-1 border-t border-slate-200 pt-3 text-xs text-slate-600">
                                {mysql.tables.map((table) => (
                                    <li key={table.name} className="flex items-center justify-between gap-2">
                                        <span className="truncate font-mono">{table.name}</span>
                                        <span className="shrink-0 text-slate-500">{formatBytes(table.size_bytes)}</span>
                                    </li>
                                ))}
                            </ul>
                        ) : null}

                        {mysql.error ? <p className="mt-2 text-xs text-rose-700">{mysql.error}</p> : null}
                    </div>

                    <div className="rounded-md border border-slate-200 bg-slate-50 p-3">
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-sm font-medium text-slate-900">MongoDB</p>
                            <span
                                className={cn('rounded-full px-2 py-0.5 text-xs font-medium', statusTone(mongodb.status))}
                            >
                                {mongodb.status === 'ok' ? 'ok' : 'error'}
                            </span>
                        </div>
                        <p className="mt-2 text-2xl font-semibold text-slate-900">{formatBytes(mongodb.size_bytes)}</p>
                        <p className="mt-1 text-xs text-slate-500">
                            {mongodb.database ?? '—'} · config store
                        </p>
                        <p className="mt-3 text-xs text-slate-600">
                            {mongodb.collections !== null ? `${mongodb.collections.toLocaleString()} collections` : '—'}
                            {' · '}
                            {mongodb.objects !== null ? `${mongodb.objects.toLocaleString()} objects` : '—'}
                        </p>
                        {mongodb.error ? <p className="mt-2 text-xs text-rose-700">{mongodb.error}</p> : null}
                    </div>
                </div>

                <div className="space-y-2">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs font-medium uppercase tracking-wide text-slate-500">Last 24 hours</p>
                        <div className="flex gap-1">
                            <Button
                                type="button"
                                size="sm"
                                variant={chartMode === 'size' ? 'primaryDark' : 'secondary'}
                                onClick={() => setChartMode('size')}
                            >
                                Size
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={chartMode === 'connections' ? 'primaryDark' : 'secondary'}
                                onClick={() => setChartMode('connections')}
                            >
                                Connections
                            </Button>
                        </div>
                    </div>

                    {showChart ? (
                        <div className="h-48 w-full">
                            <ResponsiveContainer width="100%" height="100%">
                                <AreaChart data={chartRows} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                                    <XAxis dataKey="label" tick={{ fontSize: 11, fill: '#64748b' }} />
                                    <YAxis tick={{ fontSize: 11, fill: '#64748b' }} width={48} />
                                    <Tooltip
                                        contentStyle={{ fontSize: 12 }}
                                        formatter={(value, name) => {
                                            if (typeof value !== 'number') {
                                                return ['—', String(name)];
                                            }

                                            if (chartMode === 'size') {
                                                return [`${value} MB`, String(name)];
                                            }

                                            return [value, 'Connections'];
                                        }}
                                    />
                                    {chartMode === 'size' ? (
                                        <>
                                            <Area
                                                type="monotone"
                                                dataKey="mysql_size_mb"
                                                name="MySQL"
                                                stroke="#0891b2"
                                                fill="#0891b2"
                                                fillOpacity={0.15}
                                                strokeWidth={2}
                                                connectNulls
                                            />
                                            <Area
                                                type="monotone"
                                                dataKey="mongodb_size_mb"
                                                name="MongoDB"
                                                stroke="#7c3aed"
                                                fill="#7c3aed"
                                                fillOpacity={0.12}
                                                strokeWidth={2}
                                                connectNulls
                                            />
                                        </>
                                    ) : (
                                        <Area
                                            type="monotone"
                                            dataKey="mysql_connections"
                                            name="MySQL connections"
                                            stroke="#0f766e"
                                            fill="#0f766e"
                                            fillOpacity={0.15}
                                            strokeWidth={2}
                                            connectNulls
                                        />
                                    )}
                                </AreaChart>
                            </ResponsiveContainer>
                        </div>
                    ) : (
                        <p className="rounded-md border border-dashed border-slate-200 bg-white px-3 py-6 text-center text-xs text-slate-500">
                            Chart needs at least two samples (about one minute of polling).
                        </p>
                    )}
                </div>
            </div>
        </Card>
    );
}
