import type { ReactNode } from 'react';

import { cn } from '@/lib/cn';

export interface EmptyStateProps {
    title: string;
    description?: ReactNode;
    action?: ReactNode;
    className?: string;
}

export default function EmptyState({ title, description, action, className }: EmptyStateProps) {
    return (
        <div className={cn('mx-auto flex max-w-md flex-col items-center gap-2 px-4 py-2 text-center', className)}>
            <p className="text-sm font-medium text-slate-800">{title}</p>
            {description ? <div className="text-sm text-slate-500">{description}</div> : null}
            {action ? <div className="mt-2">{action}</div> : null}
        </div>
    );
}
