import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';

import {
    PageShell,
    PageShellCanvas,
    PageShellControlBar,
    PageShellIdentity,
} from '@/Components/layout/page-shell';
import SyncBatchesPanel from '@/Components/Sync/SyncBatchesPanel';
import SyncIntegrityPanel from '@/Components/Sync/SyncIntegrityPanel';
import SyncIntegritySummary from '@/Components/Sync/SyncIntegritySummary';
import SegmentedControl from '@/Components/ui/SegmentedControl';
import { useIntegrityReport } from '@/hooks/useIntegrityReport';
import AppLayout from '@/Layouts/AppLayout';
import type { FlickrAccount, PageProps } from '@/types';

interface Props extends PageProps {
    accounts: FlickrAccount[];
}

export default function Sync() {
    return (
        <AppLayout>
            <SyncPageBody />
        </AppLayout>
    );
}

function SyncPageBody() {
    const { accounts } = usePage<Props>().props;
    const [tab, setTab] = useState<'batches' | 'integrity'>('batches');
    const integrity = useIntegrityReport();

    return (
        <>
            <Head title="Sync" />

            <PageShell data-testid="sync-page">
                <PageShellIdentity
                    breadcrumbs={[{ label: 'Operations', href: '/operations' }, { label: 'Sync' }]}
                    title="Sync"
                    subtitle="Track transfer progress, download/upload history, and manage local storage integrity."
                />

                <PageShellControlBar
                    actions={
                        <SegmentedControl
                            value={tab}
                            options={[
                                { value: 'batches', label: 'Batches' },
                                { value: 'integrity', label: 'Integrity' },
                            ]}
                            onChange={setTab}
                        />
                    }
                />

                <PageShellCanvas className="space-y-6" variant="plain">
                    <SyncIntegritySummary
                        report={integrity.report}
                        loading={integrity.loading}
                        scanning={integrity.scanning}
                        onScan={() => void integrity.triggerScan()}
                    />

                    {tab === 'batches' ? (
                        <SyncBatchesPanel accounts={accounts} />
                    ) : (
                        <SyncIntegrityPanel
                            report={integrity.report}
                            loading={integrity.loading}
                            fixing={integrity.fixing}
                            onFix={(actions) => void integrity.executeFixActions(actions)}
                        />
                    )}
                </PageShellCanvas>
            </PageShell>
        </>
    );
}
