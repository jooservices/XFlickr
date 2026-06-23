import { Link } from '@inertiajs/react';
import { Fragment } from 'react';

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

interface BreadcrumbsProps {
    items: BreadcrumbItem[];
}

export default function Breadcrumbs({ items }: BreadcrumbsProps) {
    return (
        <>
            {items.map((item, index) => (
                <Fragment key={`${item.label}-${index}`}>
                    {index > 0 ? ' / ' : null}
                    {item.href ? (
                        <Link href={item.href} className="hover:underline">
                            {item.label}
                        </Link>
                    ) : (
                        item.label
                    )}
                </Fragment>
            ))}
        </>
    );
}
