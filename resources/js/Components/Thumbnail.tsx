import { Link } from '@inertiajs/react';
import type { KeyboardEvent } from 'react';

import { cn } from '@/lib/cn';

type ThumbnailSize = 'sm' | 'md' | 'lg';

interface ThumbnailProps {
    url: string | null;
    alt: string;
    href?: string | null;
    linkHref?: string | null;
    size?: ThumbnailSize;
    onClick?: () => void;
}

const sizeClasses: Record<ThumbnailSize, string> = {
    sm: 'h-12 w-12',
    md: 'h-20 w-20',
    lg: 'h-24 w-24',
};

export default function Thumbnail({
    url,
    alt,
    href,
    linkHref,
    size = 'sm',
    onClick,
}: ThumbnailProps) {
    const frameClass = sizeClasses[size];

    if (!url) {
        return <div className={cn(frameClass, 'rounded bg-slate-100')} aria-hidden />;
    }

    const isClickable = Boolean(href || linkHref || onClick);

    const image = (
        <img
            src={url}
            alt={alt}
            className={cn(
                frameClass,
                'rounded object-cover bg-slate-100',
                isClickable ? 'transition-shadow hover:ring-2 hover:ring-cyan-300' : undefined,
            )}
            loading="lazy"
        />
    );

    if (linkHref) {
        return (
            <Link href={linkHref} className="inline-block cursor-pointer">
                {image}
            </Link>
        );
    }

    if (href) {
        return (
            <a href={href} target="_blank" rel="noreferrer" className="inline-block cursor-pointer">
                {image}
            </a>
        );
    }

    if (onClick) {
        const handleKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                onClick();
            }
        };

        return (
            <button
                type="button"
                className="inline-block cursor-pointer rounded p-0"
                onClick={onClick}
                onKeyDown={handleKeyDown}
                aria-label={`Open ${alt}`}
            >
                {image}
            </button>
        );
    }

    return image;
}
