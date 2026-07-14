import Button from '@/Components/ui/Button';
import { cn } from '@/lib/cn';

export interface SegmentedControlOption<T extends string> {
    value: T;
    label: string;
}

export interface SegmentedControlProps<T extends string> {
    value: T;
    options: readonly SegmentedControlOption<T>[];
    onChange: (value: T) => void;
    className?: string;
    size?: 'sm' | 'md';
}

export default function SegmentedControl<T extends string>({
    value,
    options,
    onChange,
    className,
    size = 'sm',
}: SegmentedControlProps<T>) {
    return (
        <div className={cn('inline-flex rounded-lg border border-slate-200 bg-white p-0.5', className)}>
            {options.map((option) => (
                <Button
                    key={option.value}
                    type="button"
                    variant="ghost"
                    size={size}
                    onClick={() => onChange(option.value)}
                    className={cn(
                        'min-w-20 capitalize',
                        value === option.value ? 'bg-cyan-50 text-cyan-800' : 'text-slate-600',
                    )}
                >
                    {option.label}
                </Button>
            ))}
        </div>
    );
}
