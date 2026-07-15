import { useCallback, useEffect, useMemo, useState } from 'react';

export type TableSelectionState = 'none' | 'partial' | 'all';

export type TableSelectionScope = 'keys' | 'matching';

export interface DataTableSelectionProps<T> {
    selectedKeys: Set<string>;
    onToggle: (key: string) => void;
    onTogglePage: () => void;
    /** Replace selection (exits matching scope). Used by JOO DataTable. */
    onChange: (keys: Set<string>) => void;
    selectionState: TableSelectionState;
    isRowSelectable?: (row: T) => boolean;
    rowLabel?: (row: T) => string;
    scope: TableSelectionScope;
    matchingTotal: number | null;
    displayCount: number;
    canSelectMatching: boolean;
    onSelectMatching: () => void;
}

export interface UseTableSelectionOptions<T> {
    rowKey: (row: T) => string;
    rows: T[];
    isRowSelectable?: (row: T) => boolean;
    clearWhen?: unknown;
    /** Total rows matching current filters (for select-all-matching). */
    matchingTotal?: number | null;
    /** When false, hide/disable the matching CTA even if totals allow it. */
    allowSelectMatching?: boolean;
}

export function useTableSelection<T>({
    rowKey,
    rows,
    isRowSelectable,
    clearWhen,
    matchingTotal = null,
    allowSelectMatching = true,
}: UseTableSelectionOptions<T>) {
    const [selectedKeys, setSelectedKeys] = useState<Set<string>>(() => new Set());
    const [scope, setScope] = useState<TableSelectionScope>('keys');

    useEffect(() => {
        setSelectedKeys(new Set());
        setScope('keys');
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
        if (scope === 'matching') {
            return 'all';
        }

        if (selectableKeys.length === 0 || selectedOnPageCount === 0) {
            return 'none';
        }

        if (selectedOnPageCount === selectableKeys.length) {
            return 'all';
        }

        return 'partial';
    }, [scope, selectableKeys.length, selectedOnPageCount]);

    const resolvedMatchingTotal =
        matchingTotal !== null && matchingTotal !== undefined && matchingTotal > 0 ? matchingTotal : null;

    const canSelectMatching =
        allowSelectMatching &&
        scope !== 'matching' &&
        selectionState === 'all' &&
        resolvedMatchingTotal !== null &&
        resolvedMatchingTotal > selectableKeys.length;

    const displayCount = scope === 'matching' && resolvedMatchingTotal !== null ? resolvedMatchingTotal : selectedKeys.size;

    const isSelected = useCallback(
        (key: string) => scope === 'matching' || selectedKeys.has(key),
        [scope, selectedKeys],
    );

    const toggle = useCallback(
        (key: string) => {
            if (scope === 'matching') {
                setScope('keys');
                setSelectedKeys(new Set(selectableKeys.filter((candidate) => candidate !== key)));

                return;
            }

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
        [scope, selectableKeys],
    );

    const togglePage = useCallback(() => {
        if (scope === 'matching') {
            setScope('keys');
            setSelectedKeys(new Set());

            return;
        }

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
    }, [scope, selectionState, selectableKeys]);

    const selectMatching = useCallback(() => {
        if (!allowSelectMatching || resolvedMatchingTotal === null || resolvedMatchingTotal <= selectableKeys.length) {
            return;
        }

        setScope('matching');
        setSelectedKeys(new Set(selectableKeys));
    }, [allowSelectMatching, resolvedMatchingTotal, selectableKeys]);

    const clear = useCallback(() => {
        setSelectedKeys(new Set());
        setScope('keys');
    }, []);

    const replaceKeys = useCallback((keys: Set<string>) => {
        setScope('keys');
        setSelectedKeys(new Set(keys));
    }, []);

    const selectedRows = useMemo(
        () => rows.filter((row) => isSelected(rowKey(row))),
        [rows, rowKey, isSelected],
    );

    const tableSelection: DataTableSelectionProps<T> = useMemo(
        () => ({
            selectedKeys,
            onToggle: toggle,
            onTogglePage: togglePage,
            onChange: replaceKeys,
            selectionState,
            isRowSelectable,
            rowLabel: rowKey,
            scope,
            matchingTotal: resolvedMatchingTotal,
            displayCount,
            canSelectMatching,
            onSelectMatching: selectMatching,
        }),
        [
            selectedKeys,
            toggle,
            togglePage,
            replaceKeys,
            selectionState,
            isRowSelectable,
            rowKey,
            scope,
            resolvedMatchingTotal,
            displayCount,
            canSelectMatching,
            selectMatching,
        ],
    );

    return {
        selectedKeys,
        selectedRows,
        selectedCount: displayCount,
        selectedOnPageCount,
        hasSelection: displayCount > 0,
        selectionState,
        scope,
        isMatching: scope === 'matching',
        matchingTotal: resolvedMatchingTotal,
        canSelectMatching,
        selectMatching,
        isSelected,
        toggle,
        togglePage,
        clear,
        tableSelection,
    };
}
