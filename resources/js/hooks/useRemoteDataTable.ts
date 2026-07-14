import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import type { SortDirection } from '@/Components/ui/DataTable';
import { apiGet } from '@/lib/apiClient';
import type { PaginatedMeta } from '@/types';

interface ApiListResponse<T> {
    data: T[];
    meta: PaginatedMeta;
}

export type PaginationMode = 'replace' | 'append';

export interface UseRemoteDataTableOptions {
    fetchPath: string;
    initialSort?: string;
    initialDirection?: SortDirection;
    perPage?: number;
    filters?: Record<string, string>;
    paginationMode?: PaginationMode;
}

export function useRemoteDataTable<T>({
    fetchPath,
    initialSort = 'id',
    initialDirection = 'desc',
    perPage = 25,
    filters = {},
    paginationMode = 'replace',
}: UseRemoteDataTableOptions) {
    const [data, setData] = useState<T[]>([]);
    const [meta, setMeta] = useState<PaginatedMeta | null>(null);
    const [page, setPageState] = useState(1);
    const [sortKey, setSortKey] = useState(initialSort);
    const [sortDirection, setSortDirection] = useState<SortDirection>(initialDirection);
    const [loading, setLoading] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const appendRef = useRef(false);

    // Stable serialized key so parent identity churn of `filters` does not reload.
    const filtersKey = useMemo(() => JSON.stringify(filters), [filters]);
    const stableFilters = useMemo(
        () => JSON.parse(filtersKey) as Record<string, string>,
        [filtersKey],
    );

    const handleSortChange = useCallback((key: string, direction: SortDirection) => {
        appendRef.current = false;
        setSortKey(key);
        setSortDirection(direction);
        setPageState(1);
    }, []);

    const load = useCallback(async () => {
        const shouldAppend = appendRef.current && paginationMode === 'append';

        if (shouldAppend) {
            setLoadingMore(true);
        } else {
            setLoading(true);
        }

        const params: Record<string, string | number> = {
            page,
            per_page: perPage,
            sort: sortKey,
            direction: sortDirection,
        };

        for (const [key, value] of Object.entries(stableFilters)) {
            if (value.trim() !== '') {
                params[key] = value.trim();
            }
        }

        try {
            const json = await apiGet<ApiListResponse<T>>(fetchPath, { params });
            setData((current) => (shouldAppend ? [...current, ...json.data] : json.data));
            setMeta(json.meta);
            if (json.meta.sort) {
                setSortKey(json.meta.sort);
            }
            if (json.meta.direction) {
                setSortDirection(json.meta.direction);
            }
        } finally {
            appendRef.current = false;
            setLoading(false);
            setLoadingMore(false);
        }
    }, [fetchPath, page, perPage, sortKey, sortDirection, stableFilters, paginationMode]);

    useEffect(() => {
        void load();
    }, [load]);

    const applyFilters = useCallback(() => {
        appendRef.current = false;
        setPageState(1);
    }, []);

    const setPage = useCallback((next: number | ((previous: number) => number)) => {
        appendRef.current = false;
        setPageState(next);
    }, []);

    const loadMore = useCallback(() => {
        if (meta === null || page >= meta.last_page || loading || loadingMore) {
            return;
        }

        appendRef.current = true;
        setPageState((previous) => previous + 1);
    }, [loading, loadingMore, meta, page]);

    const reset = useCallback(() => {
        appendRef.current = false;
        setPageState(1);
    }, []);

    const hasMore = meta !== null && page < meta.last_page;

    return {
        data,
        meta,
        page,
        setPage,
        loading,
        loadingMore,
        hasMore,
        loadMore,
        reset,
        sortKey,
        sortDirection,
        handleSortChange,
        reload: load,
        applyFilters,
    };
}
