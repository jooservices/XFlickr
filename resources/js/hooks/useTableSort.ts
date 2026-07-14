import { useCallback, useState } from 'react';

import type { SortDirection } from '@/Components/ui/DataTable';

export interface UseTableSortOptions {
    initialSort?: string;
    initialDirection?: SortDirection;
}

export function useTableSort({
    initialSort = 'id',
    initialDirection = 'desc',
}: UseTableSortOptions = {}) {
    const [sortKey, setSortKey] = useState(initialSort);
    const [sortDirection, setSortDirection] = useState<SortDirection>(initialDirection);

    const handleSortChange = useCallback((key: string, direction: SortDirection) => {
        setSortKey(key);
        setSortDirection(direction);
    }, []);

    return {
        sortKey,
        sortDirection,
        handleSortChange,
        setSortKey,
        setSortDirection,
    };
}
