import type { ReactNode } from 'react';

import Card from '@/Components/ui/Card';
import { cn } from '@/lib/cn';

type Tone = 'slate' | 'cyan' | 'violet' | 'amber' | 'emerald' | 'rose';

const toneClass: Record<Tone, { badge: string; value: string }> = {
    slate: { badge: 'bg-slate-100 text-slate-700', value: 'text-slate-900' },
    cyan: { badge: 'bg-cyan-100 text-cyan-800', value: 'text-slate-900' },
    violet: { badge: 'bg-violet-100 text-violet-800', value: 'text-slate-900' },
    amber: { badge: 'bg-amber-100 text-amber-800', value: 'text-slate-900' },
    emerald: { badge: 'bg-emerald-100 text-emerald-800', value: 'text-slate-900' },
    rose: { badge: 'bg-rose-100 text-rose-800', value: 'text-slate-900' },
};

export default function StatCard({
    label,
    value,
    hint,
    icon,
    tone = 'slate',
    className,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    icon?: ReactNode;
    tone?: Tone;
    className?: string;
}) {
    return (
        <Card className={cn('shadow-sm', className)} showFooter={false}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        {icon ? (
                            <span className={cn('inline-flex rounded-md px-2 py-1 text-xs font-medium', toneClass[tone].badge)}>
                                {icon}
                            </span>
                        ) : null}
                        <p className="text-sm font-medium text-slate-700">{label}</p>
                    </div>
                    <p className={cn('mt-2 text-3xl font-bold tabular-nums', toneClass[tone].value)}>{value}</p>
                    {hint !== undefined && hint !== null ? (
                        <div className="mt-1 text-xs text-slate-500">{hint}</div>
                    ) : null}
                </div>
            </div>
        </Card>
    );
}

