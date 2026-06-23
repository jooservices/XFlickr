import FilterBar from '@/Components/FilterBar';
import SearchField from '@/Components/SearchField';

export interface CatalogOwnerNsidFilterProps {
    value: string;
    onChange: (value: string) => void;
    onSubmit: () => void;
    onClear?: () => void;
    placeholder?: string;
}

export default function CatalogOwnerNsidFilter({
    value,
    onChange,
    onSubmit,
    onClear,
    placeholder = 'Filter by owner NSID',
}: CatalogOwnerNsidFilterProps) {
    return (
        <FilterBar onSubmit={onSubmit} onClear={onClear}>
            <SearchField
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                containerClassName="min-w-64 flex-1"
            />
        </FilterBar>
    );
}
