import type { KeyboardEvent, ReactNode } from 'react';

import { cn } from '@/lib/cn';

export interface PhotoGridTileProps {
    imageUrl: string | null;
    alt: string;
    topLeft?: ReactNode;
    topRight?: ReactNode;
    bottomLeft?: ReactNode;
    bottomRight?: ReactNode;
    center?: ReactNode;
    topRow?: ReactNode;
    bottomRow?: ReactNode;
    revealTopRowOnHover?: boolean;
    revealBottomRowOnHover?: boolean;
    onClick?: () => void;
    className?: string;
}

function rowRevealClasses(revealOnHover: boolean): string {
    return cn(
        'transition-opacity duration-150',
        revealOnHover &&
            'pointer-events-none opacity-0 group-hover:pointer-events-auto group-hover:opacity-100 [@media(hover:none)]:pointer-events-auto [@media(hover:none)]:opacity-100',
    );
}

export default function PhotoGridTile({
    imageUrl,
    alt,
    topLeft,
    topRight,
    bottomLeft,
    bottomRight,
    center,
    topRow,
    bottomRow,
    revealTopRowOnHover = false,
    revealBottomRowOnHover = false,
    onClick,
    className,
}: PhotoGridTileProps) {
    const hasHoverOverlay = Boolean(topRow || bottomRow || revealTopRowOnHover || revealBottomRowOnHover);
    const isClickable = onClick !== undefined;

    const handleKeyDown = (event: KeyboardEvent<HTMLElement>) => {
        if (!onClick) {
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            onClick();
        }
    };

    return (
        <figure
            className={cn(
                'group relative overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900',
                isClickable && 'cursor-pointer',
                className,
            )}
            onClick={onClick}
            onKeyDown={isClickable ? handleKeyDown : undefined}
            role={isClickable ? 'button' : undefined}
            tabIndex={isClickable ? 0 : undefined}
            aria-label={isClickable ? `Open ${alt}` : undefined}
        >
            <div className="relative aspect-square overflow-hidden bg-slate-100 dark:bg-slate-800">
                {imageUrl ? (
                    <img
                        src={imageUrl}
                        alt={alt}
                        className="absolute inset-0 h-full w-full object-cover"
                        loading="lazy"
                    />
                ) : (
                    <div className="absolute inset-0 bg-slate-100 dark:bg-slate-800" aria-hidden />
                )}

                {hasHoverOverlay ? (
                    <div
                        className="pointer-events-none absolute inset-0 bg-gradient-to-b from-black/45 via-transparent to-black/45 opacity-0 transition-opacity group-hover:opacity-100 [@media(hover:none)]:opacity-100"
                        aria-hidden
                    />
                ) : null}

                {topRow ? (
                    <div
                        className={cn(
                            'absolute inset-x-0 top-0 z-10 flex items-center gap-1 p-1.5',
                            rowRevealClasses(revealTopRowOnHover),
                        )}
                    >
                        {topRow}
                    </div>
                ) : null}

                {topLeft ? <div className="absolute left-1 top-1 z-10">{topLeft}</div> : null}
                {topRight ? <div className="absolute right-1 top-1 z-10">{topRight}</div> : null}
                {bottomLeft ? <div className="absolute bottom-1 left-1 z-10">{bottomLeft}</div> : null}
                {bottomRight ? <div className="absolute bottom-1 right-1 z-10">{bottomRight}</div> : null}

                {center ? (
                    <div className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center [&>*]:pointer-events-auto">
                        {center}
                    </div>
                ) : null}

                {bottomRow ? (
                    <div
                        className={cn(
                            'absolute inset-x-0 bottom-0 z-10 flex items-center gap-1 p-1.5',
                            rowRevealClasses(revealBottomRowOnHover),
                        )}
                    >
                        {bottomRow}
                    </div>
                ) : null}
            </div>
        </figure>
    );
}
