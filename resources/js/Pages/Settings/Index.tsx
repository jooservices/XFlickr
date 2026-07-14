import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useCallback, useState } from 'react';

import { PageShell, PageShellCanvas, PageShellIdentity } from '@/Components/Layout/page-shell';
import GeneralConfigPanel from '@/Components/Settings/GeneralConfigPanel';
import OnboardingWizard from '@/Components/Settings/OnboardingWizard';
import Button from '@/Components/ui/Button';
import AppLayout from '@/Layouts/AppLayout';
import { settingsCrumbs } from '@/lib/breadcrumbs';
import type { PageProps, RuntimeConfigPayload } from '@/types';

interface Props extends PageProps {
    tab: 'general';
    runtime_config: RuntimeConfigPayload;
    runtime_config_available: boolean;
    has_flickr_accounts: boolean;
    has_storage_accounts: boolean;
    has_completed_crawl: boolean;
}

export default function SettingsIndex({
    runtime_config,
    runtime_config_available,
    has_flickr_accounts,
    has_storage_accounts,
    has_completed_crawl,
}: Props) {
    const [openCreate, setOpenCreate] = useState<(() => void) | null>(null);

    const handleOpenCreateReady = useCallback((next: (() => void) | null) => {
        setOpenCreate(() => next);
    }, []);

    return (
        <AppLayout>
            <Head title="Settings" />

            <PageShell>
                <PageShellIdentity
                    breadcrumbs={settingsCrumbs()}
                    title="Settings"
                    subtitle="Configure runtime options for crawl, discovery, and operations."
                    actions={
                        runtime_config_available ? (
                            <Button
                                type="button"
                                variant="primary"
                                onClick={() => openCreate?.()}
                                disabled={openCreate === null}
                            >
                                <Plus className="h-4 w-4" />
                                New
                            </Button>
                        ) : undefined
                    }
                />

                <PageShellCanvas className="space-y-6" variant="plain">
                    <OnboardingWizard
                        hasFlickrAccounts={has_flickr_accounts}
                        hasStorageAccounts={has_storage_accounts}
                        hasCompletedCrawl={has_completed_crawl}
                    />

                    <GeneralConfigPanel
                        curated={runtime_config.curated}
                        custom={runtime_config.custom}
                        runtimeConfigAvailable={runtime_config_available}
                        onOpenCreateReady={handleOpenCreateReady}
                    />
                </PageShellCanvas>
            </PageShell>
        </AppLayout>
    );
}
