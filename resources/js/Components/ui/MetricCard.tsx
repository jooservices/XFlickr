import type { ReactNode } from 'react';

import Card from '@/Components/ui/Card';
import { cn } from '@/lib/cn';

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
    tone?: Tone;
    className?: string;
};

function formatMetricValue(value: ReactNode): ReactNode {
    if (typeof value === 'number') {
        return value.toLocaleString();
    }

    return value;
}

export default function MetricCard({ label, value, hint, tone = 'slate', className }: MetricCardProps) {
    return (
        <Card className={cn('shadow-sm', className)} showFooter={false}>
            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
            <p className={cn('mt-2 text-2xl font-semibold', toneValueClass[tone])}>{formatMetricValue(value)}</p>
            {hint ? <p className="mt-1 text-xs text-slate-500">{hint}</p> : null}
        </Card>
    );
}
