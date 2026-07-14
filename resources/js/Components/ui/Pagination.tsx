import Button from '@/Components/ui/Button';
import { cn } from '@/lib/cn';
import type { PaginatedMeta } from '@/types';

export interface PaginationProps {
    meta: PaginatedMeta;
    onPageChange: (page: number) => void;
}

type PageItem = number | 'ellipsis';

function buildPageNumbers(current: number, last: number): PageItem[] {
    if (last <= 1) {
        return [1];
    }

    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const pages: PageItem[] = [1];

    if (current > 3) {
        pages.push('ellipsis');
    }

    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    for (let page = start; page <= end; page++) {
        pages.push(page);
    }

    if (current < last - 2) {
        pages.push('ellipsis');
    }

    pages.push(last);

    return pages;
}

export default function Pagination({ meta, onPageChange }: PaginationProps) {
    const pages = buildPageNumbers(meta.current_page, meta.last_page);
    const itemLabel = meta.total === 1 ? 'item' : 'items';

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
            <div className="flex flex-wrap items-center gap-1">
                <Button
                    variant="secondary"
                    size="sm"
                    disabled={meta.current_page <= 1}
                    onClick={() => onPageChange(meta.current_page - 1)}
                >
                    Previous
                </Button>

                {meta.last_page > 1 ? (
                    <div className="flex items-center gap-1 px-1">
                        {pages.map((page, index) =>
                            page === 'ellipsis' ? (
                                <span key={`ellipsis-${index}`} className="px-1 text-slate-400">
                                    …
                                </span>
                            ) : (
                                <button
                                    key={page}
                                    type="button"
                                    disabled={page === meta.current_page}
                                    onClick={() => onPageChange(page)}
                                    className={cn(
                                        'min-w-8 rounded-md border px-2 py-1.5 text-xs font-medium transition-colors',
                                        page === meta.current_page
                                            ? 'border-slate-900 bg-slate-900 text-white'
                                            : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                                    )}
                                    aria-current={page === meta.current_page ? 'page' : undefined}
                                >
                                    {page}
                                </button>
                            ),
                        )}
                    </div>
                ) : null}

                <Button
                    variant="secondary"
                    size="sm"
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => onPageChange(meta.current_page + 1)}
                >
                    Next
                </Button>
            </div>

            <span className="text-slate-600">
                {meta.total.toLocaleString()} {itemLabel}
            </span>
        </div>
    );
}
