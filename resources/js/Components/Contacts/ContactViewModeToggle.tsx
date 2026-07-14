import SegmentedControl from '@/Components/ui/SegmentedControl';

export type ContactViewMode = 'table' | 'graph';

interface ContactViewModeToggleProps {
    value: ContactViewMode;
    onChange: (mode: ContactViewMode) => void;
}

const OPTIONS = [
    { value: 'table' as const, label: 'Table' },
    { value: 'graph' as const, label: 'Graph' },
];

export default function ContactViewModeToggle({ value, onChange }: ContactViewModeToggleProps) {
    return <SegmentedControl value={value} options={OPTIONS} onChange={onChange} />;
}
