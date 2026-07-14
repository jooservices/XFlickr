import { useEffect, useMemo, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import RateLimitMeter from '@/Components/Flickr/RateLimitMeter';
import Card from '@/Components/ui/Card';
import { useFlickrApiUsage } from '@/hooks/useFlickrApiUsage';
import { flickrAccountLabel } from '@/hooks/useFlickrRateLimit';
import { cn } from '@/lib/cn';
import {
    resolveDefaultFlickrQuotaNsid,
    writeStoredFlickrQuotaNsid,
} from '@/lib/flickrQuotaAccount';
import type { ApiUsageBucket, FlickrAccount } from '@/types';

interface ApiUsageChartProps {
    accounts: FlickrAccount[];
    activeConnectionKey?: string | null;
    hours?: number;
}

interface ChartRow {
    label: string;
    requests: number;
    isCurrent: boolean;
    fill: string;
}

function formatHourLabel(hourStart: string): string {
    return new Date(hourStart).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function barFill(requests: number, maxRequests: number, isCurrent: boolean): string {
    if (maxRequests > 0 && requests >= maxRequests) {
        return '#ef4444';
    }

    if (maxRequests > 0 && requests / maxRequests >= 0.8) {
        return '#f59e0b';
    }

    if (isCurrent) {
        return '#0891b2';
    }

    return '#94a3b8';
}

function toChartRows(buckets: ApiUsageBucket[], maxRequests: number): ChartRow[] {
    return buckets.map((bucket) => ({
        label: formatHourLabel(bucket.hour_start),
        requests: bucket.requests,
        isCurrent: bucket.is_current,
        fill: barFill(bucket.requests, maxRequests, bucket.is_current),
    }));
}

export default function ApiUsageChart({ accounts, activeConnectionKey = null, hours = 24 }: ApiUsageChartProps) {
    const [selectedNsid, setSelectedNsid] = useState<string | null>(() =>
        resolveDefaultFlickrQuotaNsid(accounts, activeConnectionKey),
    );

    useEffect(() => {
        setSelectedNsid((current) => {
            if (current && accounts.some((account) => account.nsid === current)) {
                return current;
            }

            return resolveDefaultFlickrQuotaNsid(accounts, activeConnectionKey);
        });
    }, [accounts, activeConnectionKey]);

    const { snapshot, loading } = useFlickrApiUsage(selectedNsid, hours);

    const chartRows = useMemo(
        () => (snapshot ? toChartRows(snapshot.buckets, snapshot.max_requests_per_hour) : []),
        [snapshot],
    );

    const totalRequests = useMemo(
        () => chartRows.reduce((sum, row) => sum + row.requests, 0),
        [chartRows],
    );

    const showAccountPicker = accounts.length > 1;

    if (accounts.length === 0) {
        return null;
    }

    return (
        <Card
            title="API usage (hourly)"
            subtitle={`Flickr API calls per hour for the last ${hours} hours. Refreshes every 5 seconds.`}
            showFooter={false}
            headerActions={
                showAccountPicker ? (
                    <select
                        value={selectedNsid ?? ''}
                        onChange={(event) => {
                            setSelectedNsid(event.target.value);
                            writeStoredFlickrQuotaNsid(event.target.value);
                        }}
                        className="max-w-[12rem] truncate rounded-md border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700"
                        aria-label="Flickr account for API usage chart"
                    >
                        {accounts.map((account) => (
                            <option key={account.nsid} value={account.nsid}>
                                {flickrAccountLabel(account)}
                            </option>
                        ))}
                    </select>
                ) : null
            }
        >
            <div
                className={cn('space-y-4', loading && 'opacity-80')}
                aria-live="polite"
                aria-busy={loading}
            >
                {snapshot && totalRequests === 0 ? (
                    <p className="text-sm text-slate-500">No API calls in the last {hours} hours.</p>
                ) : null}

                <div className="h-64 w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={chartRows} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" vertical={false} />
                            <XAxis
                                dataKey="label"
                                tick={{ fill: '#64748b', fontSize: 11 }}
                                interval="preserveStartEnd"
                                minTickGap={16}
                            />
                            <YAxis
                                tick={{ fill: '#64748b', fontSize: 11 }}
                                allowDecimals={false}
                                width={40}
                            />
                            <Tooltip
                                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                                formatter={(value: any) => [Number(value).toLocaleString(), 'Requests']}
                                labelFormatter={(label) => `Hour ${label}`}
                                contentStyle={{
                                    borderRadius: '0.375rem',
                                    borderColor: '#e2e8f0',
                                    fontSize: '12px',
                                }}
                            />
                            {snapshot ? (
                                <ReferenceLine
                                    y={snapshot.max_requests_per_hour}
                                    stroke="#f59e0b"
                                    strokeDasharray="4 4"
                                    label={{
                                        value: 'Hourly limit',
                                        position: 'insideTopRight',
                                        fill: '#b45309',
                                        fontSize: 11,
                                    }}
                                />
                            ) : null}
                            <Bar dataKey="requests" radius={[4, 4, 0, 0]} maxBarSize={32}>
                                {chartRows.map((row, index) => (
                                    <Cell key={`${row.label}-${index}`} fill={row.fill} />
                                ))}
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </div>

                {snapshot ? (
                    <RateLimitMeter
                        rateLimit={snapshot.rate_limit}
                        label="Current rolling window"
                        compact
                    />
                ) : null}
            </div>
        </Card>
    );
}
