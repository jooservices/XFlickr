import { useCallback, useEffect, useMemo, useState } from 'react';

export type TableSelectionState = 'none' | 'partial' | 'all';

export interface DataTableSelectionProps<T> {
    selectedKeys: Set<string>;
    onToggle: (key: string) => void;
    onTogglePage: () => void;
    selectionState: TableSelectionState;
    isRowSelectable?: (row: T) => boolean;
    rowLabel?: (row: T) => string;
}

export interface UseTableSelectionOptions<T> {
    rowKey: (row: T) => string;
    rows: T[];
    isRowSelectable?: (row: T) => boolean;
    clearWhen?: unknown;
}

export function useTableSelection<T>({
    rowKey,
    rows,
    isRowSelectable,
    clearWhen,
}: UseTableSelectionOptions<T>) {
    const [selectedKeys, setSelectedKeys] = useState<Set<string>>(() => new Set());

    useEffect(() => {
        setSelectedKeys(new Set());
    }, [clearWhen]);

    const selectableRows = useMemo(
        () => rows.filter((row) => (isRowSelectable ? isRowSelectable(row) : true)),
        [rows, isRowSelectable],
    );

    const selectableKeys = useMemo(
        () => selectableRows.map((row) => rowKey(row)),
        [selectableRows, rowKey],
    );

    const selectedOnPageCount = useMemo(
        () => selectableKeys.filter((key) => selectedKeys.has(key)).length,
        [selectableKeys, selectedKeys],
    );

    const selectionState: TableSelectionState = useMemo(() => {
        if (selectableKeys.length === 0 || selectedOnPageCount === 0) {
            return 'none';
        }

        if (selectedOnPageCount === selectableKeys.length) {
            return 'all';
        }

        return 'partial';
    }, [selectableKeys.length, selectedOnPageCount]);

    const isSelected = useCallback((key: string) => selectedKeys.has(key), [selectedKeys]);

    const toggle = useCallback(
        (key: string) => {
            setSelectedKeys((current) => {
                const next = new Set(current);

                if (next.has(key)) {
                    next.delete(key);
                } else {
                    next.add(key);
                }

                return next;
            });
        },
        [],
    );

    const togglePage = useCallback(() => {
        setSelectedKeys((current) => {
            const next = new Set(current);

            if (selectionState === 'all') {
                for (const key of selectableKeys) {
                    next.delete(key);
                }

                return next;
            }

            for (const key of selectableKeys) {
                next.add(key);
            }

            return next;
        });
    }, [selectionState, selectableKeys]);

    const clear = useCallback(() => {
        setSelectedKeys(new Set());
    }, []);

    const selectedRows = useMemo(
        () => rows.filter((row) => selectedKeys.has(rowKey(row))),
        [rows, rowKey, selectedKeys],
    );

    const tableSelection: DataTableSelectionProps<T> = useMemo(
        () => ({
            selectedKeys,
            onToggle: toggle,
            onTogglePage: togglePage,
            selectionState,
            isRowSelectable,
            rowLabel: rowKey,
        }),
        [selectedKeys, toggle, togglePage, selectionState, isRowSelectable, rowKey],
    );

    return {
        selectedKeys,
        selectedRows,
        selectedCount: selectedKeys.size,
        selectedOnPageCount,
        hasSelection: selectedKeys.size > 0,
        selectionState,
        isSelected,
        toggle,
        togglePage,
        clear,
        tableSelection,
    };
}
