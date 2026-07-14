import { Search } from 'lucide-react';
import { forwardRef } from 'react';

import Input, { type InputProps } from '@/Components/ui/Input';
import { cn } from '@/lib/cn';
import type { InputSize } from '@/lib/inputVariants';

export interface SearchFieldProps extends Omit<InputProps, 'size'> {
    size?: InputSize;
    containerClassName?: string;
}

const iconSizeClasses = {
    sm: 'left-2 size-3.5',
    md: 'left-3 size-4',
} as const;

const paddingClasses = {
    sm: 'pl-8 pr-2',
    md: 'pl-9 pr-3',
} as const;

const SearchField = forwardRef<HTMLInputElement, SearchFieldProps>(function SearchField(
    { size = 'md', className, containerClassName, ...props },
    ref,
) {
    return (
        <div className={cn('relative', containerClassName)}>
            <Search
                className={cn(
                    'pointer-events-none absolute top-1/2 -translate-y-1/2 text-slate-400',
                    iconSizeClasses[size],
                )}
                aria-hidden
            />
            <Input
                ref={ref}
                size={size}
                className={cn(paddingClasses[size], className)}
                {...props}
            />
        </div>
    );
});

export default SearchField;
