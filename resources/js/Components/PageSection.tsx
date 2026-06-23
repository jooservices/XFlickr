import type { ReactNode } from 'react';

interface PageSectionProps {
    title: string;
    description: string;
    children: ReactNode;
}

export default function PageSection({ title, description, children }: PageSectionProps) {
    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-lg font-medium text-slate-900">{title}</h2>
                <p className="mt-0.5 text-sm text-slate-600">{description}</p>
            </div>
            {children}
        </section>
    );
}
