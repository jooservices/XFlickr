import Button from '@/Components/Button';

interface OnboardingWizardProps {
    hasFlickrAccounts: boolean;
    hasStorageAccounts: boolean;
}

export default function OnboardingWizard({ hasFlickrAccounts, hasStorageAccounts }: OnboardingWizardProps) {
    if (hasFlickrAccounts && hasStorageAccounts) {
        return null;
    }

    return (
        <div className="rounded-lg border border-cyan-200 bg-cyan-50 p-5 dark:border-cyan-900 dark:bg-cyan-950/40">
            <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Getting started</h2>
            <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">
                Connect Flickr, run your first crawl, then optionally add cloud storage for backups.
            </p>
            <ol className="mt-4 space-y-3 text-sm text-slate-700 dark:text-slate-200">
                <li className="flex items-start gap-2">
                    <span className="font-mono text-xs text-cyan-700 dark:text-cyan-300">1</span>
                    <span>
                        {hasFlickrAccounts ? 'Flickr connected.' : 'Add Flickr API credentials and connect an account.'}
                    </span>
                </li>
                <li className="flex items-start gap-2">
                    <span className="font-mono text-xs text-cyan-700 dark:text-cyan-300">2</span>
                    <span>From Contacts or Catalog, trigger a manual crawl for the contacts you want to archive.</span>
                </li>
                <li className="flex items-start gap-2">
                    <span className="font-mono text-xs text-cyan-700 dark:text-cyan-300">3</span>
                    <span>
                        {hasStorageAccounts
                            ? 'Storage connected — queue uploads from Contacts or Catalog.'
                            : 'Optional: connect Google Photos, Drive, OneDrive, or R2 under Storages.'}
                    </span>
                </li>
            </ol>
            {!hasFlickrAccounts ? (
                <div className="mt-4">
                    <Button type="button" variant="primary" onClick={() => window.location.assign('/flickr/oauth')}>
                        Connect Flickr
                    </Button>
                </div>
            ) : (
                <div className="mt-4">
                    <Button type="button" variant="secondary" onClick={() => window.location.assign('/contacts')}>
                        Open Contacts
                    </Button>
                </div>
            )}
        </div>
    );
}
