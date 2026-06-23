import type { ReactNode } from 'react';

import { ActionButton } from '@/Components/ActionBar';
import Button from '@/Components/Button';
import type { ButtonVariant } from '@/lib/buttonVariants';
import { cn } from '@/lib/cn';

export interface BulkActionContext<T> {
    selectedRows: T[];
    selectedKeys: string[];
}

export interface BulkAction<T> {
    id: string;
    label: string;
    icon?: ReactNode;
    variant?: ButtonVariant;
    disabled?: boolean | ((context: BulkActionContext<T>) => boolean);
    menu?: ReactNode | ((context: BulkActionContext<T>) => ReactNode);
    onAction?: (context: BulkActionContext<T>) => void;
}

export interface BulkActionBarProps<T> {
    selectedCount: number;
    selectedRows: T[];
    selectedKeys: string[];
    actions: BulkAction<T>[];
    onClear: () => void;
    className?: string;
}

function resolveDisabled<T>(action: BulkAction<T>, context: BulkActionContext<T>): boolean {
    if (typeof action.disabled === 'function') {
        return action.disabled(context);
    }

    return action.disabled ?? false;
}

export default function BulkActionBar<T>({
    selectedCount,
    selectedRows,
    selectedKeys,
    actions,
    onClear,
    className,
}: BulkActionBarProps<T>) {
    if (selectedCount === 0) {
        return null;
    }

    const context: BulkActionContext<T> = {
        selectedRows,
        selectedKeys,
    };

    return (
        <div
            role="toolbar"
            aria-label="Bulk actions"
            className={cn(
                'flex flex-wrap items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3',
                className,
            )}
        >
            <p className="text-sm font-medium text-slate-700 tabular-nums" aria-live="polite">
                {selectedCount} selected
            </p>

            <div className="flex flex-wrap items-center gap-2">
                {actions.map((action) => {
                    const disabled = resolveDisabled(action, context);
                    const menu =
                        typeof action.menu === 'function' ? action.menu(context) : action.menu;

                    if (menu !== undefined) {
                        return (
                            <ActionButton
                                key={action.id}
                                label={action.label}
                                icon={action.icon}
                                variant={action.variant}
                                size="sm"
                                disabled={disabled}
                                menu={menu}
                            />
                        );
                    }

                    return (
                        <ActionButton
                            key={action.id}
                            label={action.label}
                            icon={action.icon}
                            variant={action.variant}
                            size="sm"
                            disabled={disabled}
                            onClick={() => action.onAction?.(context)}
                        />
                    );
                })}
            </div>

            <Button
                type="button"
                variant="ghost"
                size="sm"
                className="ml-auto text-slate-600"
                onClick={onClear}
            >
                Clear
            </Button>
        </div>
    );
}
