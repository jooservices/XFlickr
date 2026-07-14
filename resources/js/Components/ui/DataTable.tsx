import type { ReactNode } from 'react';

import BulkActionBar from '@/Components/ui/BulkActionBar';
import type { BulkAction } from '@/Components/ui/BulkActionBar';
import BusyRegion from '@/Components/ui/BusyRegion';
import Checkbox from '@/Components/ui/Checkbox';
import Pagination from '@/Components/ui/Pagination';
import type { DataTableSelectionProps } from '@/hooks/useTableSelection';
import { cn } from '@/lib/cn';
import type { PaginatedMeta } from '@/types';


export type SortDirection = 'asc' | 'desc';

export interface DataTableColumn<T> {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'right' | 'center';
    className?: string;
    render: (row: T) => ReactNode;
}

export type { DataTableSelectionProps } from '@/hooks/useTableSelection';

export interface DataTableProps<T> {
    columns: DataTableColumn<T>[];
    data: T[];
    rowKey: (row: T) => string;
    sortKey?: string;
    sortDirection?: SortDirection;
    onSortChange?: (key: string, direction: SortDirection) => void;
    emptyMessage?: ReactNode;
    actionsColumn?: (row: T) => ReactNode;
    actionsLabel?: string;
    meta?: PaginatedMeta;
    onPageChange?: (page: number) => void;
    selection?: DataTableSelectionProps<T>;
    bulkActions?: BulkAction<T>[];
    onBulkClear?: () => void;
    toolbar?: ReactNode;
    /** When true, page wait if empty, otherwise overlay spinner on the table. */
    busy?: boolean;
    busyLabel?: string;
    matchingLabel?: string;
}

function nextDirection(currentKey: string, columnKey: string, currentDirection: SortDirection): SortDirection {
    if (currentKey === columnKey) {
        return currentDirection === 'asc' ? 'desc' : 'asc';
    }

    return 'asc';
}

function sortIndicator(active: boolean, direction: SortDirection): string {
    if (!active) {
        return ' ↕';
    }

    return direction === 'asc' ? ' ↑' : ' ↓';
}

export default function DataTable<T>({
    columns,
    data,
    rowKey,
    sortKey,
    sortDirection = 'asc',
    onSortChange,
    emptyMessage = 'No data.',
    actionsColumn,
    actionsLabel = 'Actions',
    meta,
    onPageChange,
    selection,
    bulkActions,
    onBulkClear,
    toolbar,
    busy = false,
    busyLabel = 'Loading…',
    matchingLabel = 'items',
}: DataTableProps<T>) {
    const hasSelection = selection !== undefined;
    const colSpan = columns.length + (actionsColumn ? 1 : 0) + (hasSelection ? 1 : 0);
    const isMatching = selection?.scope === 'matching';

    const selectedRows = selection
        ? data.filter((row) => isMatching || selection.selectedKeys.has(rowKey(row)))
        : [];
    const selectedKeys = selection ? [...selection.selectedKeys] : [];
    const hasBulkSelection =
        Boolean(selection && bulkActions && onBulkClear && selection.displayCount > 0);

    const bulkBar = hasBulkSelection ? (
            <BulkActionBar
                className="border-b-0"
                selectedCount={selection!.displayCount}
                selectedRows={selectedRows}
                selectedKeys={selectedKeys}
                actions={bulkActions!}
                onClear={onBulkClear!}
                isMatching={isMatching}
                canSelectMatching={selection!.canSelectMatching}
                matchingTotal={selection!.matchingTotal}
                onSelectMatching={selection!.onSelectMatching}
                matchingLabel={matchingLabel}
            />
        ) : (
            toolbar ?? null
        );

    return (
        <BusyRegion busy={busy} empty={data.length === 0} label={busyLabel}>
            <div className="overflow-hidden rounded-lg border border-slate-200 bg-white">
                {bulkBar ? (
                    <div className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 shadow-sm">
                        {bulkBar}
                    </div>
                ) : null}
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                {hasSelection ? (
                                    <th className="w-10 px-3 py-3">
                                        <Checkbox
                                            checked={selection.selectionState === 'all'}
                                            indeterminate={selection.selectionState === 'partial'}
                                            onChange={selection.onTogglePage}
                                            aria-label={
                                                selection.scope === 'matching'
                                                    ? 'Clear matching selection'
                                                    : 'Select all on this page'
                                            }
                                            disabled={data.length === 0}
                                        />
                                    </th>
                                ) : null}
                                {columns.map((column) => {
                                    const align = column.align ?? 'left';
                                    const isActive = sortKey === column.key;

                                    return (
                                        <th
                                            key={column.key}
                                            className={cn(
                                                'px-4 py-3 font-medium text-slate-600',
                                                align === 'right' && 'text-right',
                                                align === 'center' && 'text-center',
                                                align === 'left' && 'text-left',
                                                column.className,
                                            )}
                                        >
                                            {column.sortable && onSortChange ? (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        onSortChange(
                                                            column.key,
                                                            nextDirection(sortKey ?? '', column.key, sortDirection),
                                                        )
                                                    }
                                                    className="inline-flex items-center gap-0.5 hover:text-slate-900"
                                                >
                                                    {column.label}
                                                    <span className="text-xs text-slate-400">
                                                        {sortIndicator(isActive, sortDirection)}
                                                    </span>
                                                </button>
                                            ) : (
                                                column.label
                                            )}
                                        </th>
                                    );
                                })}
                                {actionsColumn ? (
                                    <th className="px-4 py-3 text-right font-medium text-slate-600">{actionsLabel}</th>
                                ) : null}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {data.length === 0 ? (
                                <tr>
                                    <td colSpan={colSpan} className="px-4 py-8 text-center text-slate-500">
                                        {emptyMessage}
                                    </td>
                                </tr>
                            ) : (
                                data.map((row) => {
                                    const key = rowKey(row);
                                    const selectable = selection?.isRowSelectable ? selection.isRowSelectable(row) : true;
                                    const checked =
                                        selection !== undefined &&
                                        (selection.scope === 'matching' || selection.selectedKeys.has(key));
                                    const rowLabel = selection?.rowLabel?.(row) ?? key;

                                    return (
                                        <tr
                                            key={key}
                                            className={cn(
                                                'hover:bg-slate-50',
                                                checked && 'bg-cyan-50/40',
                                                !selectable && 'opacity-60',
                                            )}
                                        >
                                            {hasSelection ? (
                                                <td className="w-10 px-3 py-3 align-top">
                                                    <Checkbox
                                                        checked={checked}
                                                        disabled={!selectable}
                                                        onChange={() => selection.onToggle(key)}
                                                        aria-label={`Select ${rowLabel}`}
                                                        title={
                                                            !selectable
                                                                ? 'Unavailable while an operation is in progress'
                                                                : undefined
                                                        }
                                                    />
                                                </td>
                                            ) : null}
                                            {columns.map((column) => {
                                                const align = column.align ?? 'left';

                                                return (
                                                    <td
                                                        key={column.key}
                                                        className={cn(
                                                            'px-4 py-3 align-top',
                                                            align === 'right' && 'text-right',
                                                            align === 'center' && 'text-center',
                                                            column.className,
                                                        )}
                                                    >
                                                        {column.render(row)}
                                                    </td>
                                                );
                                            })}
                                            {actionsColumn ? (
                                                <td className="px-4 py-3 text-right align-top">{actionsColumn(row)}</td>
                                            ) : null}
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
                {meta && onPageChange ? (
                    <div className="border-t border-slate-200 px-4 py-3">
                        <Pagination meta={meta} onPageChange={onPageChange} />
                    </div>
                ) : null}
            </div>
        </BusyRegion>
    );
}
