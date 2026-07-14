import type { ReactNode } from 'react';

import LoadingIndicator, { PageLoading, type LoadingIndicatorSize } from '@/Components/ui/LoadingIndicator';
import { cn } from '@/lib/cn';

export interface BusyRegionProps {
    /** When true, show a wait affordance for this region. */
    busy: boolean;
    /**
     * True when the region has no useful content yet.
     * Uses a centered page-style wait instead of overlaying children.
     */
    empty?: boolean;
    label?: string;
    size?: LoadingIndicatorSize;
    className?: string;
    children: ReactNode;
}

/**
 * Scoped wait for a panel, table, or card group.
 * - `busy && empty` → page-style centered indicator (primary content not ready)
 * - `busy && !empty` → overlay spinner; children stay visible underneath
 */
export default function BusyRegion({
    busy,
    empty = false,
    label = 'Loading…',
    size = 'md',
    className,
    children,
}: BusyRegionProps) {
    if (busy && empty) {
        return <PageLoading label={label} size={size} className={className} />;
    }

    return (
        <div className={cn('relative', className)} aria-busy={busy || undefined}>
            {children}
            {busy ? (
                <div
                    className="absolute inset-0 z-10 flex items-center justify-center rounded-[inherit] bg-white/70"
                    aria-hidden
                >
                    <LoadingIndicator label={label} size={size} />
                </div>
            ) : null}
        </div>
    );
}
