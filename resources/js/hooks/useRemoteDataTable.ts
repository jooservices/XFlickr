import { useCallback, useEffect, useMemo, useState } from 'react';

import type { SortDirection } from '@/Components/DataTable';
import { apiGet } from '@/lib/apiClient';
import type { PaginatedMeta } from '@/types';

interface ApiListResponse<T> {
    data: T[];
    meta: PaginatedMeta;
}

export interface UseRemoteDataTableOptions {
    fetchPath: string;
    initialSort?: string;
    initialDirection?: SortDirection;
    perPage?: number;
    filters?: Record<string, string>;
}

export function useRemoteDataTable<T>({
    fetchPath,
    initialSort = 'id',
    initialDirection = 'desc',
    perPage = 25,
    filters = {},
}: UseRemoteDataTableOptions) {
    const [data, setData] = useState<T[]>([]);
    const [meta, setMeta] = useState<PaginatedMeta | null>(null);
    const [page, setPage] = useState(1);
    const [sortKey, setSortKey] = useState(initialSort);
    const [sortDirection, setSortDirection] = useState<SortDirection>(initialDirection);
    const [loading, setLoading] = useState(true);

    const filtersKey = useMemo(() => JSON.stringify(filters), [filters]);

    const handleSortChange = useCallback((key: string, direction: SortDirection) => {
        setSortKey(key);
        setSortDirection(direction);
        setPage(1);
    }, []);

    const load = useCallback(async () => {
        setLoading(true);

        const params: Record<string, string | number> = {
            page,
            per_page: perPage,
            sort: sortKey,
            direction: sortDirection,
        };

        for (const [key, value] of Object.entries(filters)) {
            if (value.trim() !== '') {
                params[key] = value.trim();
            }
        }

        try {
            const json = await apiGet<ApiListResponse<T>>(fetchPath, { params });
            setData(json.data);
            setMeta(json.meta);
            if (json.meta.sort) {
                setSortKey(json.meta.sort);
            }
            if (json.meta.direction) {
                setSortDirection(json.meta.direction);
            }
        } finally {
            setLoading(false);
        }
    }, [fetchPath, page, perPage, sortKey, sortDirection, filtersKey]);

    useEffect(() => {
        void load();
    }, [load]);

    const applyFilters = useCallback(() => {
        setPage(1);
    }, []);

    return {
        data,
        meta,
        page,
        setPage,
        loading,
        sortKey,
        sortDirection,
        handleSortChange,
        reload: load,
        applyFilters,
    };
}
