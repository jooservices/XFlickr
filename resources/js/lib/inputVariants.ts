import { cn } from '@/lib/cn';

export type InputSize = 'sm' | 'md';

const sizeClasses: Record<InputSize, string> = {
    sm: 'py-1.5 text-sm',
    md: 'py-2 text-sm',
};

export function inputVariants({
    size = 'md',
    className,
}: {
    size?: InputSize;
    className?: string;
}): string {
    return cn(
        'w-full rounded-md border border-slate-200 bg-white px-3 text-slate-900 placeholder:text-slate-400',
        'focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400',
        'disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-50',
        sizeClasses[size],
        className,
    );
}
