import { cn } from '@/lib/cn';

export type ButtonVariant =
    | 'primary'
    | 'primaryDark'
    | 'secondary'
    | 'connect'
    | 'destructive'
    | 'warning'
    | 'ghost'
    | 'link';

export type ButtonSize = 'xs' | 'sm' | 'md' | 'lg';

const variantClasses: Record<ButtonVariant, string> = {
    primary:
        'rounded-md bg-cyan-700 font-medium text-white hover:bg-cyan-800 disabled:opacity-50',
    primaryDark:
        'rounded-md bg-slate-900 font-medium text-white hover:bg-slate-800 disabled:opacity-50',
    secondary:
        'rounded-md border border-slate-200 bg-white font-medium hover:bg-slate-50 disabled:opacity-50',
    connect:
        'inline-flex items-center gap-1.5 rounded-md border border-cyan-200 bg-cyan-50 font-medium text-cyan-800 hover:bg-cyan-100 disabled:opacity-50',
    destructive:
        'inline-flex items-center gap-1.5 rounded-md border border-red-200 font-medium text-red-700 hover:bg-red-50 disabled:opacity-50',
    warning:
        'rounded-md border border-amber-200 font-medium text-amber-800 hover:bg-amber-50 disabled:opacity-50',
    ghost:
        'rounded-md font-medium text-slate-500 hover:bg-slate-100 disabled:opacity-50',
    link: 'font-medium text-blue-600 hover:underline disabled:opacity-50',
};

const sizeClasses: Record<ButtonSize, string> = {
    xs: 'px-2.5 py-1.5 text-xs',
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-3 py-2 text-sm',
    lg: 'px-4 py-2 text-sm',
};

export function buttonVariants({
    variant = 'secondary',
    size = 'md',
    className,
}: {
    variant?: ButtonVariant;
    size?: ButtonSize;
    className?: string;
}): string {
    const base = 'inline-flex items-center justify-center gap-1.5 transition-colors';

    if (variant === 'link') {
        return cn(base, variantClasses.link, size === 'xs' ? 'text-xs' : 'text-sm', className);
    }

    if (variant === 'ghost' && size === 'xs') {
        return cn(base, 'p-1', variantClasses.ghost, className);
    }

    return cn(base, variantClasses[variant], sizeClasses[size], className);
}
