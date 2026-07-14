import type { SortDirection } from '@/Components/ui/DataTable';

export function sortClientData<T>(
    data: T[],
    sortKey: string,
    sortDirection: SortDirection,
    valueForKey: (row: T, key: string) => string | number | null | undefined,
): T[] {
    const sorted = [...data];

    sorted.sort((left, right) => {
        const leftValue = valueForKey(left, sortKey);
        const rightValue = valueForKey(right, sortKey);

        if (leftValue === rightValue) {
            return 0;
        }

        if (leftValue === null || leftValue === undefined) {
            return 1;
        }

        if (rightValue === null || rightValue === undefined) {
            return -1;
        }

        if (typeof leftValue === 'number' && typeof rightValue === 'number') {
            return sortDirection === 'asc' ? leftValue - rightValue : rightValue - leftValue;
        }

        const comparison = String(leftValue).localeCompare(String(rightValue), undefined, {
            numeric: true,
            sensitivity: 'base',
        });

        return sortDirection === 'asc' ? comparison : -comparison;
    });

    return sorted;
}

export function defaultRowValue(row: Record<string, unknown>, key: string): string | number | null | undefined {
    const value = row[key];

    if (typeof value === 'number' || typeof value === 'string') {
        return value;
    }

    if (typeof value === 'boolean') {
        return value ? 1 : 0;
    }

    return null;
}
