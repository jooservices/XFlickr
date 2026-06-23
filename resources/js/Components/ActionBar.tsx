import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { buttonVariants, type ButtonSize, type ButtonVariant } from '@/lib/buttonVariants';
import { cn } from '@/lib/cn';

export interface ActionButtonProps {
    label: string;
    icon?: ReactNode;
    onClick?: () => void;
    disabled?: boolean;
    variant?: ButtonVariant;
    size?: ButtonSize;
    menu?: ReactNode;
    menuMinWidth?: string;
    alignMenu?: 'left' | 'right';
}

export function ActionButton({
    label,
    icon,
    onClick,
    disabled = false,
    variant = 'secondary',
    size = 'sm',
    menu,
    menuMinWidth = 'min-w-52',
    alignMenu = 'right',
}: ActionButtonProps) {
    const hasMenu = menu !== undefined;
    const [open, setOpen] = useState(false);
    const [menuPosition, setMenuPosition] = useState<{ top: number; left: number } | null>(null);
    const containerRef = useRef<HTMLDivElement>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    const updateMenuPosition = () => {
        const button = buttonRef.current;
        const menuEl = menuRef.current;
        if (!button || !menuEl) {
            return;
        }

        const rect = button.getBoundingClientRect();
        const menuWidth = menuEl.offsetWidth;
        const menuHeight = menuEl.offsetHeight;
        const gap = 4;
        const padding = 8;

        let top = rect.bottom + gap;
        if (top + menuHeight > window.innerHeight - padding) {
            top = Math.max(padding, rect.top - menuHeight - gap);
        }

        let left = alignMenu === 'right' ? rect.right - menuWidth : rect.left;
        left = Math.max(padding, Math.min(left, window.innerWidth - menuWidth - padding));

        setMenuPosition({ top, left });
    };

    useLayoutEffect(() => {
        if (!open) {
            setMenuPosition(null);
            return;
        }

        updateMenuPosition();
    }, [open, menu]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const handleClickOutside = (event: MouseEvent) => {
            const target = event.target as Node;
            if (containerRef.current?.contains(target) || menuRef.current?.contains(target)) {
                return;
            }

            setOpen(false);
        };

        const handleReposition = () => updateMenuPosition();

        document.addEventListener('mousedown', handleClickOutside);
        window.addEventListener('resize', handleReposition);
        window.addEventListener('scroll', handleReposition, true);

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            window.removeEventListener('resize', handleReposition);
            window.removeEventListener('scroll', handleReposition, true);
        };
    }, [open]);

    const handleClick = () => {
        if (hasMenu) {
            setOpen((value) => !value);
            return;
        }

        onClick?.();
    };

    const menuPortal =
        open && hasMenu ? (
            <div
                ref={menuRef}
                style={
                    menuPosition
                        ? { position: 'fixed', top: menuPosition.top, left: menuPosition.left }
                        : { position: 'fixed', top: -9999, left: -9999, visibility: 'hidden' as const }
                }
                className={cn('z-50 overflow-hidden rounded-md border border-slate-200 bg-white shadow-lg', menuMinWidth)}
                onClick={() => setOpen(false)}
            >
                {menu}
            </div>
        ) : null;

    return (
        <div ref={containerRef} className="relative inline-block">
            <button
                ref={buttonRef}
                type="button"
                disabled={disabled}
                onClick={handleClick}
                className={buttonVariants({ variant, size })}
            >
                {icon}
                {label}
                {hasMenu ? <ChevronDown className={size === 'md' ? 'size-3.5' : 'size-3'} /> : null}
            </button>
            {menuPortal && createPortal(menuPortal, document.body)}
        </div>
    );
}

export interface ActionBarProps {
    children: ReactNode;
    className?: string;
}

export default function ActionBar({ children, className }: ActionBarProps) {
    return <div className={cn('flex flex-wrap items-center gap-2', className)}>{children}</div>;
}
