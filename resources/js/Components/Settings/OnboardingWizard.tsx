import { Check, Circle, X } from 'lucide-react';
import { useMemo, useState } from 'react';

import Button from '@/Components/Button';
import { connectionsPath } from '@/lib/connections';
import {
    buildOnboardingSteps,
    shouldShowOnboarding,
    type OnboardingFlags,
} from '@/lib/onboardingProgress';
import { dismissSettingsOnboarding, isSettingsOnboardingDismissed } from '@/lib/settingsOnboarding';

export default function OnboardingWizard({
    hasFlickrAccounts,
    hasStorageAccounts,
    hasCompletedCrawl = false,
}: OnboardingFlags) {
    const [dismissed, setDismissed] = useState(isSettingsOnboardingDismissed);

    const flags = useMemo(
        () => ({ hasFlickrAccounts, hasStorageAccounts, hasCompletedCrawl }),
        [hasFlickrAccounts, hasStorageAccounts, hasCompletedCrawl],
    );

    const steps = useMemo(() => buildOnboardingSteps(flags), [flags]);

    if (!shouldShowOnboarding(flags, dismissed)) {
        return null;
    }

    const handleDismiss = () => {
        dismissSettingsOnboarding();
        setDismissed(true);
    };

    return (
        <div
            className="relative rounded-lg border border-cyan-200 bg-cyan-50 p-5 dark:border-cyan-900 dark:bg-cyan-950/40"
            data-testid="onboarding-wizard"
        >
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
                {steps.map((step, index) => (
                    <li key={step.id} className="flex items-start gap-2">
                        {step.done ? (
                            <Check
                                className="mt-0.5 size-4 shrink-0 text-cyan-700 dark:text-cyan-300"
                                aria-hidden
                            />
                        ) : (
                            <Circle
                                className="mt-0.5 size-4 shrink-0 text-slate-400"
                                aria-hidden
                            />
                        )}
                        <span>
                            <span className="sr-only">Step {index + 1}. </span>
                            {step.label}
                            {step.optional && !step.done ? (
                                <span className="ml-1 text-xs text-slate-500">(optional)</span>
                            ) : null}
                        </span>
                    </li>
                ))}
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
            ) : !hasCompletedCrawl ? (
                <div className="mt-4 flex flex-wrap gap-2">
                    <Button type="button" variant="primary" onClick={() => window.location.assign('/contacts')}>
                        Open Contacts
                    </Button>
                    {!hasStorageAccounts ? (
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => window.location.assign(connectionsPath({ provider: 'storage' }))}
                        >
                            Connect Storage
                        </Button>
                    ) : null}
                </div>
            ) : !hasStorageAccounts ? (
                <div className="mt-4 flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="primary"
                        onClick={() => window.location.assign(connectionsPath({ provider: 'storage' }))}
                    >
                        Connect Storage
                    </Button>
                    <Button type="button" variant="secondary" onClick={() => window.location.assign('/contacts')}>
                        Open Contacts
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
