import { cn } from '@/lib/cn';

interface ProgressBarProps {
    value: number;
    max?: number;
    className?: string;
    showLabel?: boolean;
}

export default function ProgressBar({ value, max = 100, className, showLabel = true }: ProgressBarProps) {
    const percent = max > 0 ? Math.min(100, Math.round((value / max) * 100)) : 0;

    return (
        <div className={cn('space-y-1', className)}>
            <div className="h-2 overflow-hidden rounded-full bg-slate-200">
                <div
                    className="h-full rounded-full bg-blue-600 transition-all duration-300"
                    style={{ width: `${percent}%` }}
                />
            </div>
            {showLabel && (
                <p className="text-xs text-slate-500">
                    {value} / {max} ({percent}%)
                </p>
            )}
        </div>
    );
}
