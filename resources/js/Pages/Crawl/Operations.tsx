import { Head, usePage } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { useState } from 'react';

import {
    PageShell,
    PageShellCanvas,
    PageShellControlBar,
    PageShellIdentity,
} from '@/Components/layout/page-shell';
import OperationsCrawlPanel from '@/Components/Operations/OperationsCrawlPanel';
import OperationsOverviewPanel from '@/Components/Operations/OperationsOverviewPanel';
import OperationsTransfersPanel from '@/Components/Operations/OperationsTransfersPanel';
import { useCrawlOperations } from '@/hooks/useCrawlOperations';
import AppLayout from '@/Layouts/AppLayout';
import {
    OPERATIONS_PANELS,
    readOperationsPanelFromLocation,
    writeOperationsPanelToLocation,
    type OperationsPanel,
} from '@/lib/operationsPanels';
import type { FlickrAccount, PageProps } from '@/types';

interface Props extends PageProps {
    accounts: FlickrAccount[];
}

export default function CrawlOperations() {
    const { accounts } = usePage<Props>().props;
    const {
        overview,
        dependencies,
        databases,
        accounts: opsAccounts,
        fetchRuns,
        downloadBatches,
        uploadBatches,
        activityHistory,
        loading,
    } = useCrawlOperations();
    const [panel, setPanel] = useState<OperationsPanel>(() => readOperationsPanelFromLocation());

    const selectPanel = (next: OperationsPanel) => {
        setPanel(next);
        writeOperationsPanelToLocation(next);
    };

    return (
        <AppLayout>
            <Head title="Operations" />

            <PageShell data-testid="operations-page">
                <PageShellIdentity
                    breadcrumbs={[{ label: 'Operations' }]}
                    title="Operations"
                    subtitle="Process console for crawls, transfers, and platform health. Live updates via polling."
                    actions={
                        <a
                            href="/horizon"
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            <ExternalLink className="h-4 w-4" aria-hidden />
                            Open Horizon
                        </a>
                    }
                />

                <PageShellControlBar
                    tabs={
                        <div className="flex gap-1">
                            {OPERATIONS_PANELS.map((item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() => selectPanel(item.id)}
                                    className={`border-b-2 px-4 py-2 text-sm font-medium ${
                                        panel === item.id
                                            ? 'border-slate-900 text-slate-900 dark:border-slate-100 dark:text-slate-100'
                                            : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                                    }`}
                                >
                                    {item.label}
                                </button>
                            ))}
                        </div>
                    }
                />

                <PageShellCanvas className="space-y-6" variant="plain">
                    {panel === 'overview' ? (
                        <OperationsOverviewPanel
                            overview={overview}
                            dependencies={dependencies}
                            databases={databases}
                            accounts={opsAccounts}
                            activityHistory={activityHistory}
                        />
                    ) : null}

                    {panel === 'crawl' ? (
                        <OperationsCrawlPanel
                            fetchRuns={fetchRuns}
                            accounts={accounts}
                            opsAccounts={opsAccounts}
                            loading={loading}
                        />
                    ) : null}

                    {panel === 'transfers' ? (
                        <OperationsTransfersPanel
                            downloadBatches={downloadBatches}
                            uploadBatches={uploadBatches}
                            accounts={accounts}
                            loading={loading}
                        />
                    ) : null}

                    {accounts.length > 0 ? (
                        <p className="text-xs text-slate-500">
                            Monitoring {accounts.length} account{accounts.length === 1 ? '' : 's'}.
                        </p>
                    ) : null}
                </PageShellCanvas>
            </PageShell>
        </AppLayout>
    );
}
