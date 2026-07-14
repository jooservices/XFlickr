import ProgressBar from '@/Components/ProgressBar';
import { formatCountdown, useCountdown } from '@/hooks/useCountdown';
import { cn } from '@/lib/cn';
import type { RateLimitState } from '@/types';

interface RateLimitMeterProps {
    rateLimit: RateLimitState;
    className?: string;
    compact?: boolean;
    label?: string;
    variant?: 'default' | 'navbar' | 'footer';
}

export default function RateLimitMeter({
    rateLimit,
    className,
    compact = false,
    label = 'Crawl API quota',
    variant = 'default',
}: RateLimitMeterProps) {
    const windowSeconds = useCountdown(
        rateLimit.window_reset_at,
        rateLimit.window_seconds_remaining,
    );
    const cooldownSeconds = useCountdown(
        rateLimit.cooldown_until,
        rateLimit.cooldown_seconds_remaining,
    );

    const used = rateLimit.requests_used;
    const max = rateLimit.max_requests_per_hour;
    const percent = max > 0 ? (used / max) * 100 : 0;
    const isWarning = percent >= 80 && percent < 100;
    const isFull = percent >= 100;
    const inCooldown = cooldownSeconds > 0;
    const isNavbar = variant === 'navbar';
    const isFooter = variant === 'footer';
    const showWindowReset = !isFooter && (!compact || isNavbar) && rateLimit.window_reset_at && windowSeconds > 0;
    const showCooldownDetail = !isFooter && inCooldown;
    const showGlobalPause = !isFooter && rateLimit.global_pause;

    const barTone = cn(
        '[&>div>div]:bg-blue-600',
        isWarning && '[&>div>div]:bg-amber-500',
        (isFull || inCooldown) && '[&>div>div]:bg-red-500',
    );

    if (isFooter) {
        return (
            <div className={cn('flex min-w-0 items-center gap-2', className)} title={label}>
                <span className="shrink-0 text-xs font-medium text-slate-700">{label}</span>
                <ProgressBar
                    value={used}
                    max={max}
                    showLabel={false}
                    className={cn('w-24 shrink-0 [&>div]:h-1.5', barTone)}
                />
                <span
                    className={cn(
                        'shrink-0 tabular-nums text-xs text-slate-500',
                        isFull && 'text-amber-700',
                        inCooldown && 'text-red-700',
                    )}
                >
                    {used}/{max}
                    {inCooldown ? ` · cd ${formatCountdown(cooldownSeconds)}` : ''}
                </span>
            </div>
        );
    }

    return (
        <div
            className={cn(
                isNavbar ? 'w-[220px] space-y-1' : 'space-y-1.5',
                className,
            )}
        >
            <div className="flex items-center justify-between gap-2 text-xs">
                <span className="font-medium text-slate-700">{label}</span>
                <span
                    className={cn(
                        'tabular-nums text-slate-500',
                        isFull && 'text-amber-700',
                        inCooldown && 'text-red-700',
                    )}
                >
                    {used} / {max}
                </span>
            </div>

            <ProgressBar
                value={used}
                max={max}
                showLabel={false}
                className={cn(barTone, isNavbar && '[&>div]:h-1.5')}
            />

            {showWindowReset ? (
                <p className="text-xs text-slate-500">
                    Window resets in {formatCountdown(windowSeconds)}
                </p>
            ) : null}

            {showCooldownDetail ? (
                <p className="text-xs font-medium text-red-700">
                    API cooldown — {formatCountdown(cooldownSeconds)} remaining
                </p>
            ) : null}

            {showGlobalPause ? (
                <span className="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                    Crawl paused (operator)
                </span>
            ) : null}
        </div>
    );
}
