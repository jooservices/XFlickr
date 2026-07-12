import Button from '@/Components/Button';
import { cn } from '@/lib/cn';

export type ContactViewMode = 'table' | 'graph';

interface ContactViewModeToggleProps {
    value: ContactViewMode;
    onChange: (mode: ContactViewMode) => void;
}

export default function ContactViewModeToggle({ value, onChange }: ContactViewModeToggleProps) {
    return (
        <div className="inline-flex rounded-lg border border-slate-200 bg-white p-0.5">
            {(['table', 'graph'] as const).map((mode) => (
                <Button
                    key={mode}
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => onChange(mode)}
                    className={cn(
                        'min-w-20 capitalize',
                        value === mode ? 'bg-cyan-50 text-cyan-800' : 'text-slate-600',
                    )}
                >
                    {mode}
                </Button>
            ))}
        </div>
    );
}
