import { BaseCard } from '@jooservices/react-card';
import type { ReactNode } from 'react';

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
    const resolvedTitle =
        title !== undefined || badges !== undefined ? (
            <div className="flex flex-wrap items-center gap-2">
                {title}
                {badges}
            </div>
        ) : undefined;

    const showFooterSection = footer !== undefined && footer !== null && showFooter;

    return (
        <BaseCard
            className={className}
            title={resolvedTitle}
            subtitle={subtitle}
            actions={headerActions}
            footer={showFooterSection ? footer : undefined}
        >
            {children}
        </BaseCard>
    );
}
