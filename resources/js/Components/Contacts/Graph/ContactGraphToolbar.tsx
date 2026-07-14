import { Maximize, Minimize, X } from 'lucide-react';
import type { ReactNode } from 'react';

import ContactGraphLegend from '@/Components/Contacts/Graph/ContactGraphLegend';
import Button from '@/Components/ui/Button';
import LoadingIndicator from '@/Components/ui/LoadingIndicator';

export type ContactGraphToolbarProps = {
    directShown: number;
    directTotal: number;
    nodeCount: number;
    hasMoreDirect: boolean;
    loadMoreStep: number;
    loadingMore: boolean;
    isBrowserFullscreen: boolean;
    onLoadMore: (nextLimit: number) => void;
    onShowAll: () => void;
    onToggleFullscreen: () => void;
    onExit: () => void;
    currentDirectLimit: number;
};

export default function ContactGraphToolbar({
    directShown,
    directTotal,
    nodeCount,
    hasMoreDirect,
    loadMoreStep,
    loadingMore,
    isBrowserFullscreen,
    onLoadMore,
    onShowAll,
    onToggleFullscreen,
    onExit,
    currentDirectLimit,
}: ContactGraphToolbarProps): ReactNode {
    return (
        <header className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
            <div className="min-w-0">
                <p className="truncate text-sm font-semibold text-slate-900">Contact graph</p>
                <p className="truncate text-xs text-slate-500">
                    {directShown.toLocaleString()} / {directTotal.toLocaleString()} direct contacts ·{' '}
                    {nodeCount.toLocaleString()} nodes · drag to pan · scroll to zoom
                </p>
                <ContactGraphLegend />
            </div>
            <div className="flex shrink-0 flex-wrap items-center gap-2">
                {hasMoreDirect ? (
                    <>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            disabled={loadingMore}
                            onClick={() => onLoadMore(currentDirectLimit + loadMoreStep)}
                        >
                            {loadingMore ? <LoadingIndicator size="sm" label="Loading…" /> : `+${loadMoreStep} contacts`}
                        </Button>
                        <Button type="button" variant="secondary" size="sm" disabled={loadingMore} onClick={onShowAll}>
                            Show all
                        </Button>
                    </>
                ) : null}
                <span className="hidden text-xs text-slate-500 lg:inline">Esc → exit fullscreen / table</span>
                <Button type="button" variant="secondary" size="sm" onClick={onToggleFullscreen}>
                    {isBrowserFullscreen ? (
                        <>
                            <Minimize className="mr-1 h-4 w-4" />
                            Exit fullscreen
                        </>
                    ) : (
                        <>
                            <Maximize className="mr-1 h-4 w-4" />
                            Fullscreen
                        </>
                    )}
                </Button>
                <Button type="button" variant="secondary" size="sm" onClick={onExit}>
                    <X className="mr-1 h-4 w-4" />
                    Table
                </Button>
            </div>
        </header>
    );
}
