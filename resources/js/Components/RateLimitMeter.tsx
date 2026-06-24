import ProgressBar from '@/Components/ProgressBar';
import { formatCountdown, useCountdown } from '@/hooks/useCountdown';
import { cn } from '@/lib/cn';
import type { RateLimitState } from '@/types';

interface RateLimitMeterProps {
    rateLimit: RateLimitState;
    className?: string;
    compact?: boolean;
    label?: string;
    variant?: 'default' | 'navbar';
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
    const showWindowReset = (!compact || isNavbar) && rateLimit.window_reset_at && windowSeconds > 0;

    return (
        <div
            className={cn(
                isNavbar ? 'w-[240px] space-y-1' : 'space-y-1.5',
                className,
            )}
        >
            <div className="flex items-center justify-between gap-2 text-xs">
                <span className="font-medium text-slate-700">{label}</span>
                <span className={cn('text-slate-500', isFull && 'text-amber-700', inCooldown && 'text-red-700')}>
                    {used} / {max}
                </span>
            </div>

            <ProgressBar
                value={used}
                max={max}
                showLabel={false}
                className={cn(
                    '[&>div>div]:bg-blue-600',
                    isWarning && '[&>div>div]:bg-amber-500',
                    (isFull || inCooldown) && '[&>div>div]:bg-red-500',
                    isNavbar && '[&>div]:h-1.5',
                )}
            />

            {showWindowReset ? (
                <p className="text-xs text-slate-500">
                    Window resets in {formatCountdown(windowSeconds)}
                </p>
            ) : null}

            {inCooldown ? (
                <p className="text-xs font-medium text-red-700">
                    API cooldown — {formatCountdown(cooldownSeconds)} remaining
                </p>
            ) : null}

            {rateLimit.global_pause ? (
                <span className="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                    Crawl paused (operator)
                </span>
            ) : null}
        </div>
    );
}
