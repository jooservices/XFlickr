import { useMemo } from 'react';

import type { CatalogOwnerNsidFilterProps } from '@/Components/CatalogOwnerNsidFilter';
import {
    useOwnerNsidFilter,
    type OwnerNsidFilterKey,
} from '@/hooks/useOwnerNsidFilter';
import {
    useRemoteDataTable,
    type UseRemoteDataTableOptions,
} from '@/hooks/useRemoteDataTable';

export function useCatalogOwnerNsidTable<T>(
    filterKey: OwnerNsidFilterKey,
    tableOptions: Omit<UseRemoteDataTableOptions, 'filters'>,
) {
    const { draft, setDraft, filters, apply, clear, hasActiveFilter } = useOwnerNsidFilter(filterKey);

    const table = useRemoteDataTable<T>({
        ...tableOptions,
        filters,
    });

    const filterFormProps: CatalogOwnerNsidFilterProps = useMemo(
        () => ({
            value: draft,
            onChange: setDraft,
            onSubmit: () => {
                apply();
                table.applyFilters();
            },
            onClear: hasActiveFilter
                ? () => {
                      clear();
                      table.applyFilters();
                  }
                : undefined,
        }),
        [apply, clear, draft, hasActiveFilter, setDraft, table.applyFilters],
    );

    return {
        ...table,
        filterFormProps,
    };
}
