import { Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';

export interface ValueWithExternalLinkProps {
    value: string;
    href?: string;
    externalHref?: string;
    externalTitle?: string;
    subtext?: string | null;
    mono?: boolean;
}

export default function ValueWithExternalLink({
    value,
    href,
    externalHref,
    externalTitle = 'Open on Flickr',
    subtext,
    mono = false,
}: ValueWithExternalLinkProps) {
    const valueClassName = mono
        ? 'font-mono text-xs font-medium text-slate-900 hover:underline'
        : 'font-medium text-slate-900 hover:underline';
    const plainClassName = mono ? 'font-mono text-xs text-slate-600' : 'text-slate-600';

    return (
        <div>
            <div className="flex items-center gap-1.5">
                {href ? (
                    <Link href={href} className={valueClassName}>
                        {value}
                    </Link>
                ) : (
                    <span className={plainClassName}>{value}</span>
                )}
                {externalHref ? (
                    <a
                        href={externalHref}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-slate-400 hover:text-slate-600"
                        title={externalTitle}
                    >
                        <ExternalLink className="size-3.5" />
                    </a>
                ) : null}
            </div>
            {subtext ? <div className="text-xs text-slate-500">{subtext}</div> : null}
        </div>
    );
}
