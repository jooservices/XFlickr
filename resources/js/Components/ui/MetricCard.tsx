import { StatCard, type CardTone } from '@jooservices/react-card';
import type { ReactNode } from 'react';

import { formatCount } from '@/lib/format';

type Tone = 'slate' | 'cyan' | 'violet' | 'amber' | 'emerald' | 'rose';

const toneMap: Record<Tone, CardTone> = {
    slate: 'slate',
    cyan: 'cyan',
    violet: 'slate',
    amber: 'amber',
    emerald: 'emerald',
    rose: 'rose',
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
    className,
}: MetricCardProps) {
    return (
        <StatCard
            label={label}
            value={formatMetricValue(value)}
            subtext={hint}
            footer={footer}
            tone={toneMap[tone]}
            className={className}
        />
    );
}
