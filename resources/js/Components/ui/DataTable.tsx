import { DataTable as JooDataTable } from '@jooservices/react-table';
import type { ReactNode } from 'react';

import BulkActionBar from '@/Components/ui/BulkActionBar';
import type { BulkAction } from '@/Components/ui/BulkActionBar';
import BusyRegion from '@/Components/ui/BusyRegion';
import type { DataTableSelectionProps } from '@/hooks/useTableSelection';
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
    const selectedRows = selection
        ? data.filter((row) => selection.scope === 'matching' || selection.selectedKeys.has(rowKey(row)))
        : [];
    const selectedKeysList = selection ? [...selection.selectedKeys] : [];
    const hasBulkSelection =
        Boolean(selection && bulkActions && onBulkClear && selection.displayCount > 0);

    const bulkBar = hasBulkSelection ? (
        <BulkActionBar
            className="border-b-0"
            selectedCount={selection!.displayCount}
            selectedRows={selectedRows}
            selectedKeys={selectedKeysList}
            actions={bulkActions!}
            onClear={onBulkClear!}
            isMatching={selection!.scope === 'matching'}
            canSelectMatching={selection!.canSelectMatching}
            matchingTotal={selection!.matchingTotal}
            onSelectMatching={selection!.onSelectMatching}
            matchingLabel={matchingLabel}
        />
    ) : (
        toolbar ?? null
    );

    const jooSelection = selection
        ? {
              selectedKeys:
                  selection.scope === 'matching'
                      ? new Set(data.map((row) => rowKey(row)))
                      : selection.selectedKeys,
              onChange: selection.onChange,
              isRowSelectable: selection.isRowSelectable,
              rowLabel: selection.rowLabel,
          }
        : undefined;

    return (
        <BusyRegion busy={busy} empty={data.length === 0} label={busyLabel}>
            <JooDataTable
                toolbar={bulkBar}
                columns={columns}
                data={data}
                rowKey={rowKey}
                rowActions={actionsColumn}
                actionsLabel={actionsLabel}
                emptyMessage={emptyMessage}
                loading={busy}
                sort={
                    sortKey && onSortChange
                        ? {
                              key: sortKey,
                              direction: sortDirection,
                              onChange: onSortChange,
                          }
                        : undefined
                }
                pagination={
                    meta && onPageChange
                        ? {
                              page: meta.current_page,
                              lastPage: meta.last_page,
                              total: meta.total,
                              onPageChange,
                          }
                        : undefined
                }
                selection={jooSelection}
            />
        </BusyRegion>
    );
}
