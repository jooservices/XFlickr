import ProgressBar from '@/Components/ui/ProgressBar';
import { cn } from '@/lib/cn';
import { formatBytes } from '@/lib/format';
import type { StorageQuotaState } from '@/types';

interface StorageQuotaMeterProps {
    quota: StorageQuotaState;
    className?: string;
    label?: string;
    variant?: 'default' | 'footer';
}

export default function StorageQuotaMeter({
    quota,
    className,
    label = 'Storage',
    variant = 'default',
}: StorageQuotaMeterProps) {
    const used = quota.used_bytes;
    const max = quota.limit_bytes;
    const percent = max !== null && max > 0 ? (used / max) * 100 : 0;
    const isWarning = max !== null && percent >= 80 && percent < 100;
    const isFull = max !== null && percent >= 100;
    const hasLimit = max !== null && max > 0;
    const isFooter = variant === 'footer';

    const barTone = cn(
        '[&>div>div]:bg-emerald-600',
        isWarning && '[&>div>div]:bg-amber-500',
        isFull && '[&>div>div]:bg-red-500',
    );

    if (isFooter) {
        return (
            <div className={cn('flex min-w-0 items-center gap-2', className)} title={label}>
                <span className="shrink-0 text-xs font-medium text-slate-700">{label}</span>
                {hasLimit ? (
                    <ProgressBar
                        value={used}
                        max={max}
                        showLabel={false}
                        className={cn('w-24 shrink-0 [&>div]:h-1.5', barTone)}
                    />
                ) : null}
                <span className={cn('shrink-0 tabular-nums text-xs text-slate-500', isFull && 'text-amber-700')}>
                    {hasLimit ? `${formatBytes(used)} / ${formatBytes(max)}` : formatBytes(used)}
                </span>
            </div>
        );
    }

    return (
        <div className={cn('w-[220px] space-y-1', className)}>
            <div className="flex items-center justify-between gap-2 text-xs">
                <span className="font-medium text-slate-700">{label}</span>
                <span className={cn('tabular-nums text-slate-500', isFull && 'text-amber-700')}>
                    {hasLimit ? `${formatBytes(used)} / ${formatBytes(max)}` : formatBytes(used)}
                </span>
            </div>

            {hasLimit ? (
                <ProgressBar
                    value={used}
                    max={max}
                    showLabel={false}
                    className={cn('[&>div]:h-1.5', barTone)}
                />
            ) : (
                <p className="text-[11px] text-slate-400">Unlimited / no hard limit</p>
            )}
        </div>
    );
}
