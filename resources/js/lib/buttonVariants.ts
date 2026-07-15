import { cn } from '@/lib/cn';

/**
 * Legacy XFlickr button variant names mapped onto JOO action-button classes.
 * Prefer `<Button>` / `@jooservices/react-action-buttons` for new code.
 */
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
    primary: 'joo-ab-btn joo-ab-btn--primary',
    primaryDark: 'joo-ab-btn joo-ab-btn--dark',
    secondary: 'joo-ab-btn joo-ab-btn--secondary',
    connect: 'joo-ab-btn joo-ab-btn--info',
    destructive: 'joo-ab-btn joo-ab-btn--danger',
    warning: 'joo-ab-btn joo-ab-btn--warning',
    ghost: 'joo-ab-btn joo-ab-btn--ghost',
    link: 'joo-ab-btn joo-ab-btn--ghost underline-offset-2 hover:underline',
};

const sizeClasses: Record<ButtonSize, string> = {
    xs: 'joo-ab-btn--sm',
    sm: 'joo-ab-btn--sm',
    md: 'joo-ab-btn--md',
    lg: 'joo-ab-btn--md',
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
    if (variant === 'ghost' && size === 'xs') {
        return cn(variantClasses.ghost, 'joo-ab-btn--icon', className);
    }

    return cn(variantClasses[variant], sizeClasses[size], className);
}
