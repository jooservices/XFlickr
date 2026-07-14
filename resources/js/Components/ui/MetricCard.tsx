import type { ReactNode } from 'react';

import Card from '@/Components/ui/Card';
import { cn } from '@/lib/cn';
import { formatCount } from '@/lib/format';

type Tone = 'slate' | 'cyan' | 'violet' | 'amber' | 'emerald' | 'rose';

const toneValueClass: Record<Tone, string> = {
    slate: 'text-slate-900',
    cyan: 'text-cyan-800',
    violet: 'text-violet-800',
    amber: 'text-amber-800',
    emerald: 'text-emerald-800',
    rose: 'text-rose-800',
};

export type MetricCardProps = {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    footer?: ReactNode;
    tone?: Tone;
    align?: 'start' | 'center';
    className?: string;
};

function formatMetricValue(value: ReactNode): ReactNode {
    if (typeof value === 'number') {
        return formatCount(value);
    }

    return value;
}

export default function MetricCard({
    label,
    value,
    hint,
    footer,
    tone = 'slate',
    align = 'start',
    className,
}: MetricCardProps) {
    return (
        <Card className={cn('shadow-sm', className)} footer={footer} showFooter={footer != null}>
            <div className={cn(align === 'center' && 'space-y-2 text-center')}>
                <p
                    className={cn(
                        align === 'center'
                            ? 'text-sm font-medium text-slate-700'
                            : 'text-xs font-medium uppercase tracking-wide text-slate-500',
                    )}
                >
                    {label}
                </p>
                <p
                    className={cn(
                        'font-semibold tabular-nums',
                        align === 'center' ? 'text-3xl font-bold text-slate-900' : 'mt-2 text-2xl',
                        align === 'start' && toneValueClass[tone],
                    )}
                >
                    {formatMetricValue(value)}
                </p>
                {hint ? (
                    <div className={cn('text-xs text-slate-500', align === 'start' && 'mt-1')}>{hint}</div>
                ) : null}
            </div>
        </Card>
    );
}
