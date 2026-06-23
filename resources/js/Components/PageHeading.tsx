import type { ReactNode } from 'react';

export interface PageHeadingProps {
    breadcrumbs?: ReactNode;
    title: ReactNode;
    subtitle?: ReactNode;
    actions?: ReactNode;
}

export default function PageHeading({ breadcrumbs, title, subtitle, actions }: PageHeadingProps) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                {breadcrumbs ? <div className="text-sm text-slate-500">{breadcrumbs}</div> : null}
                <h1 className="text-2xl font-semibold text-slate-900">{title}</h1>
                {subtitle ? <p className="mt-1 text-sm text-slate-600">{subtitle}</p> : null}
            </div>
            {actions ? <div className="shrink-0">{actions}</div> : null}
        </div>
    );
}
