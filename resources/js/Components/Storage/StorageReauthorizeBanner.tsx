import type { StorageAccount, StorageScopeDefinition } from '@/types';

interface StorageReauthorizeBannerProps {
    account: Pick<StorageAccount, 'label' | 'missing_scopes' | 'reauthorize_url'>;
    returnUrl?: string;
    className?: string;
}

export default function StorageReauthorizeBanner({
    account,
    returnUrl,
    className = '',
}: StorageReauthorizeBannerProps) {
    const href = returnUrl
        ? `${account.reauthorize_url}?return_url=${encodeURIComponent(returnUrl)}`
        : account.reauthorize_url;

    return (
        <div
            className={`rounded-lg border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950 ${className}`}
        >
            <p className="font-medium">Additional permissions required</p>
            <p className="mt-1">
                <span className="font-medium">{account.label}</span> needs updated access before
                XFlickr can browse uploaded photos.
            </p>

            {account.missing_scopes.length > 0 ? (
                <ul className="mt-3 list-disc space-y-1 pl-5 text-amber-900">
                    {account.missing_scopes.map((scope: StorageScopeDefinition) => (
                        <li key={scope.scope}>{scope.label}</li>
                    ))}
                </ul>
            ) : null}

            <a
                href={href}
                className="mt-4 inline-flex rounded-md bg-amber-700 px-4 py-2 text-sm font-medium text-white hover:bg-amber-800"
            >
                Reauthorize with provider
            </a>
        </div>
    );
}
