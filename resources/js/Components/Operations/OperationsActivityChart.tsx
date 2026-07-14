import { useMemo } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import type { OperationsActivityPoint } from '@/types';

interface OperationsActivityChartProps {
    history: OperationsActivityPoint[];
}

function formatTimeLabel(timestamp: number): string {
    return new Date(timestamp * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

export default function OperationsActivityChart({ history }: OperationsActivityChartProps) {
    const chartRows = useMemo(
        () =>
            history.map((point) => ({
                label: formatTimeLabel(point.t),
                running: point.runs_running,
                pending: point.pending_targets,
                transfers: point.transfers_active,
            })),
        [history],
    );

    const showChart = chartRows.length >= 2;

    return (
        <div className="space-y-2">
            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">Activity (this session)</p>
            {showChart ? (
                <div className="h-48 w-full rounded-md border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={chartRows} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                            <XAxis dataKey="label" tick={{ fontSize: 11, fill: '#64748b' }} minTickGap={24} />
                            <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: '#64748b' }} width={36} />
                            <Tooltip contentStyle={{ fontSize: 12 }} />
                            <Area
                                type="monotone"
                                dataKey="running"
                                name="Running"
                                stroke="#0891b2"
                                fill="#0891b2"
                                fillOpacity={0.15}
                                strokeWidth={2}
                            />
                            <Area
                                type="monotone"
                                dataKey="pending"
                                name="Pending"
                                stroke="#64748b"
                                fill="#64748b"
                                fillOpacity={0.08}
                                strokeWidth={2}
                            />
                            <Area
                                type="monotone"
                                dataKey="transfers"
                                name="Transfers"
                                stroke="#0f766e"
                                fill="#0f766e"
                                fillOpacity={0.12}
                                strokeWidth={2}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            ) : (
                <p className="rounded-md border border-dashed border-slate-200 bg-white px-3 py-6 text-center text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-900">
                    Chart needs a second poll (~5 seconds) while this page stays open.
                </p>
            )}
        </div>
    );
}
