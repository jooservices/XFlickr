import { X } from 'lucide-react';
import { useState } from 'react';

import Button from '@/Components/Button';
import { connectionsPath } from '@/lib/connections';
import { dismissSettingsOnboarding, isSettingsOnboardingDismissed } from '@/lib/settingsOnboarding';

interface OnboardingWizardProps {
    hasFlickrAccounts: boolean;
    hasStorageAccounts: boolean;
}

export default function OnboardingWizard({ hasFlickrAccounts, hasStorageAccounts }: OnboardingWizardProps) {
    const [dismissed, setDismissed] = useState(isSettingsOnboardingDismissed);

    if (dismissed || (hasFlickrAccounts && hasStorageAccounts)) {
        return null;
    }

    const handleDismiss = () => {
        dismissSettingsOnboarding();
        setDismissed(true);
    };

    return (
        <div className="relative rounded-lg border border-cyan-200 bg-cyan-50 p-5 dark:border-cyan-900 dark:bg-cyan-950/40">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Getting started</h2>
                    <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">
                        Connect Flickr, run your first crawl, then optionally add cloud storage for backups.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={handleDismiss}
                    className="shrink-0"
                    aria-label="Dismiss getting started"
                    title="Dismiss"
                >
                    <X className="size-4" />
                </Button>
            </div>
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
                            : 'Optional: connect Google Photos, Drive, OneDrive, or R2 under Connections → Storage.'}
                    </span>
                </li>
            </ol>
            {!hasFlickrAccounts ? (
                <div className="mt-4">
                    <Button
                        type="button"
                        variant="primary"
                        onClick={() => window.location.assign(connectionsPath({ provider: 'flickr' }))}
                    >
                        Connect Flickr
                    </Button>
                </div>
            ) : !hasStorageAccounts ? (
                <div className="mt-4 flex flex-wrap gap-2">
                    <Button type="button" variant="secondary" onClick={() => window.location.assign('/contacts')}>
                        Open Contacts
                    </Button>
                    <Button
                        type="button"
                        variant="primary"
                        onClick={() => window.location.assign(connectionsPath({ provider: 'storage' }))}
                    >
                        Connect Storage
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
