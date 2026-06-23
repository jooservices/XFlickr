import { cn } from '@/lib/cn';

interface ThumbnailProps {
    url: string | null;
    alt: string;
    href?: string | null;
}

export default function Thumbnail({ url, alt, href }: ThumbnailProps) {
    if (!url) {
        return <div className="h-12 w-12 rounded bg-slate-100" aria-hidden />;
    }

    const image = (
        <img
            src={url}
            alt={alt}
            className={cn(
                'h-12 w-12 rounded object-cover bg-slate-100',
                href ? 'transition-shadow hover:ring-2 hover:ring-cyan-300' : undefined,
            )}
            loading="lazy"
        />
    );

    if (href) {
        return (
            <a href={href} target="_blank" rel="noreferrer" className="inline-block cursor-pointer">
                {image}
            </a>
        );
    }

    return image;
}
