import {
    Button as JooButton,
    type ButtonSize as JooButtonSize,
    type ButtonVariant as JooButtonVariant,
} from '@jooservices/react-action-buttons';
import type { ButtonHTMLAttributes, ReactNode } from 'react';

import type { ButtonSize, ButtonVariant } from '@/lib/buttonVariants';

export type { ButtonSize, ButtonVariant };

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    children?: ReactNode;
    icon?: ReactNode;
}

const variantMap: Record<ButtonVariant, JooButtonVariant> = {
    primary: 'primary',
    primaryDark: 'dark',
    secondary: 'secondary',
    connect: 'info',
    destructive: 'danger',
    warning: 'warning',
    ghost: 'ghost',
    link: 'ghost',
};

const sizeMap: Record<ButtonSize, JooButtonSize> = {
    xs: 'sm',
    sm: 'sm',
    md: 'md',
    lg: 'md',
};

export default function Button({
    variant = 'secondary',
    size = 'md',
    className,
    type = 'button',
    children,
    icon,
    ...props
}: ButtonProps) {
    return (
        <JooButton
            type={type}
            variant={variantMap[variant]}
            size={size === 'xs' && !children ? 'icon' : sizeMap[size]}
            className={className}
            icon={icon}
            {...props}
        >
            {children}
        </JooButton>
    );
}
