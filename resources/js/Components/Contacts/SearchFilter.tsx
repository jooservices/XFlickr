import ContactSearchInput from '@/Components/Contacts/SearchInput';
import FilterBar from '@/Components/ui/FilterBar';

export interface ContactsSearchFilterProps {
    accountPublicId: string;
    value: string;
    onChange: (value: string) => void;
    onSubmit: () => void;
    onClear?: () => void;
}

export default function ContactsSearchFilter({
    accountPublicId,
    value,
    onChange,
    onSubmit,
    onClear,
}: ContactsSearchFilterProps) {
    return (
        <FilterBar onSubmit={onSubmit} onClear={onClear}>
            <ContactSearchInput accountPublicId={accountPublicId} value={value} onChange={onChange} />
        </FilterBar>
    );
}
