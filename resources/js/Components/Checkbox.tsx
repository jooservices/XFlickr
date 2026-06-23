import { useEffect, useRef, type InputHTMLAttributes } from 'react';

import { cn } from '@/lib/cn';

export interface CheckboxProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
    indeterminate?: boolean;
}

export default function Checkbox({ className, indeterminate, ...props }: CheckboxProps) {
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (inputRef.current) {
            inputRef.current.indeterminate = indeterminate ?? false;
        }
    }, [indeterminate]);

    return (
        <input
            ref={inputRef}
            type="checkbox"
            className={cn(
                'size-4 rounded border-slate-300 text-cyan-700 focus:ring-cyan-600 disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}
