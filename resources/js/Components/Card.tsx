import type { ReactNode } from 'react';

import { cn } from '@/lib/cn';

export interface CardProps {
    title?: ReactNode;
    subtitle?: ReactNode;
    badges?: ReactNode;
    headerActions?: ReactNode;
    footer?: ReactNode;
    showFooter?: boolean;
    children?: ReactNode;
    className?: string;
}

export default function Card({
    title,
    subtitle,
    badges,
    headerActions,
    footer,
    showFooter = true,
    children,
    className,
}: CardProps) {
    const hasHeader = title !== undefined || subtitle !== undefined || badges !== undefined || headerActions !== undefined;
    const showFooterSection = footer !== undefined && footer !== null && showFooter;

    return (
        <div className={cn('rounded-lg border border-slate-200 bg-white', className)}>
            {hasHeader ? (
                <div className="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 flex-1 space-y-1">
                        {(title !== undefined || badges !== undefined) && (
                            <div className="flex flex-wrap items-center gap-2">
                                {title !== undefined ? (
                                    <h3 className="font-medium text-slate-900">{title}</h3>
                                ) : null}
                                {badges}
                            </div>
                        )}
                        {subtitle ? <p className="text-sm text-slate-500">{subtitle}</p> : null}
                    </div>
                    {headerActions ? (
                        <div className="flex shrink-0 flex-wrap items-center gap-2">{headerActions}</div>
                    ) : null}
                </div>
            ) : null}

            {children ? <div className={cn(hasHeader ? 'p-4' : 'p-4')}>{children}</div> : null}

            {showFooterSection ? (
                <div className="border-t border-slate-100 p-4">{footer}</div>
            ) : null}
        </div>
    );
}
