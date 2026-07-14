import type { ButtonHTMLAttributes, ReactNode } from 'react';

import { buttonVariants, type ButtonSize, type ButtonVariant } from '@/lib/buttonVariants';
import { cn } from '@/lib/cn';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    children: ReactNode;
}

export default function Button({
    variant = 'secondary',
    size = 'md',
    className,
    type = 'button',
    children,
    ...props
}: ButtonProps) {
    return (
        <button type={type} className={cn(buttonVariants({ variant, size }), className)} {...props}>
            {children}
        </button>
    );
}
