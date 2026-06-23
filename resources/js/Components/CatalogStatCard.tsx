import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import Card from '@/Components/Card';
import { catalogOwnerUrl } from '@/lib/catalog';

export interface CatalogStatCardProps {
    title: string;
    dbCount: number;
    catalogPath?: string;
    ownerNsid?: string;
    sublines?: ReactNode;
}

export default function CatalogStatCard({
    title,
    dbCount,
    catalogPath,
    ownerNsid,
    sublines,
}: CatalogStatCardProps) {
    const footer =
        catalogPath && ownerNsid ? (
            <Link href={catalogOwnerUrl(catalogPath, ownerNsid)} className="text-sm font-medium text-blue-600 hover:underline">
                Browse in catalog
            </Link>
        ) : null;

    return (
        <Card title={title} footer={footer} showFooter={footer !== null}>
            <div className="space-y-2 text-center">
                <p className="text-3xl font-bold tabular-nums text-slate-900">{dbCount.toLocaleString()}</p>
                {sublines ? <div className="text-sm text-slate-500">{sublines}</div> : null}
            </div>
        </Card>
    );
}
