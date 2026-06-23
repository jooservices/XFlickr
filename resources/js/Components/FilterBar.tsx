import type { FormEvent, ReactNode } from 'react';

import Button from '@/Components/Button';
import { cn } from '@/lib/cn';

export interface FilterBarProps {
    children: ReactNode;
    onSubmit: () => void;
    onClear?: () => void;
    submitLabel?: string;
    clearLabel?: string;
    className?: string;
}

export default function FilterBar({
    children,
    onSubmit,
    onClear,
    submitLabel = 'Filter',
    clearLabel = 'Clear',
    className,
}: FilterBarProps) {
    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        onSubmit();
    };

    return (
        <form className={cn('flex flex-wrap items-center gap-2', className)} onSubmit={handleSubmit}>
            {children}
            <Button type="submit" variant="primaryDark" size="md">
                {submitLabel}
            </Button>
            {onClear ? (
                <Button type="button" variant="secondary" size="md" onClick={onClear}>
                    {clearLabel}
                </Button>
            ) : null}
        </form>
    );
}
