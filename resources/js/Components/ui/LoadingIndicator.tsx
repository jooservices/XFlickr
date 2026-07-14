import { Loader2 } from 'lucide-react';

import { cn } from '@/lib/cn';

export type LoadingIndicatorSize = 'sm' | 'md' | 'lg';

const iconSizeClass: Record<LoadingIndicatorSize, string> = {
    sm: 'size-3.5',
    md: 'size-5',
    lg: 'size-8',
};

const labelSizeClass: Record<LoadingIndicatorSize, string> = {
    sm: 'text-xs',
    md: 'text-sm',
    lg: 'text-sm',
};

export interface LoadingIndicatorProps {
    /** Visible label; omit for icon-only (still announces “Loading” to AT). */
    label?: string;
    size?: LoadingIndicatorSize;
    className?: string;
}

/**
 * Shared wait affordance for API / async work.
 * Prefer {@link PageLoading} for empty primary content and {@link BusyRegion} for scoped groups.
 */
export default function LoadingIndicator({ label, size = 'md', className }: LoadingIndicatorProps) {
    return (
        <div
            className={cn('inline-flex items-center gap-2 text-slate-500', className)}
            role="status"
            aria-live="polite"
        >
            <Loader2 className={cn(iconSizeClass[size], 'shrink-0 animate-spin')} aria-hidden />
            {label ? <span className={labelSizeClass[size]}>{label}</span> : <span className="sr-only">Loading</span>}
        </div>
    );
}

export interface PageLoadingProps {
    label?: string;
    size?: LoadingIndicatorSize;
    className?: string;
}

/** Page / canvas wait when primary content is empty and an API is required. */
export function PageLoading({ label = 'Loading…', size = 'md', className }: PageLoadingProps) {
    return (
        <div className={cn('flex min-h-48 items-center justify-center', className)}>
            <LoadingIndicator label={label} size={size} />
        </div>
    );
}
