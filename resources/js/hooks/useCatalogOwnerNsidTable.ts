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
    const { draft, setDraft, filters, apply, clear, hasActiveFilter, applied } = useOwnerNsidFilter(filterKey);

    const table = useRemoteDataTable<T>({
        ...tableOptions,
        filters,
    });
    const { applyFilters: applyTableFilters } = table;

    const filterFormProps: CatalogOwnerNsidFilterProps = useMemo(
        () => ({
            value: draft,
            onChange: setDraft,
            onSubmit: () => {
                apply();
                applyTableFilters();
            },
            onClear: hasActiveFilter
                ? () => {
                      clear();
                      applyTableFilters();
                  }
                : undefined,
        }),
        [apply, applyTableFilters, clear, draft, hasActiveFilter, setDraft],
    );

    return {
        ...table,
        filters,
        appliedOwnerNsid: applied,
        filterFormProps,
    };
}
