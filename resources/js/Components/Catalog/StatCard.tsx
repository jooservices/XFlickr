import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import MetricCard from '@/Components/ui/MetricCard';
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
            <Link
                href={catalogOwnerUrl(catalogPath, ownerNsid)}
                className="text-sm font-medium text-cyan-700 hover:underline"
            >
                Browse in catalog
            </Link>
        ) : null;

    return (
        <MetricCard label={title} value={dbCount} hint={sublines} footer={footer} align="center" />
    );
}
