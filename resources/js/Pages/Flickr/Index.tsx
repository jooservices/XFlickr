import { Head, Link } from '@inertiajs/react';

import { PageShell, PageShellCanvas, PageShellIdentity } from '@/Components/layout/page-shell';
import AccountOpsCard from '@/Components/macros/AccountOpsCard';
import { useFlickrCrawlSummaries } from '@/hooks/useFlickrCrawlSummaries';
import AppLayout from '@/Layouts/AppLayout';
import { flickrRootCrumb } from '@/lib/breadcrumbs';
import type { FlickrAccountSummary, PageProps } from '@/types';

interface Props extends PageProps {
    accounts: FlickrAccountSummary[];
}

export default function FlickrIndex({ accounts }: Props) {
    const summaries = useFlickrCrawlSummaries(accounts);

    return (
        <AppLayout>
            <Head title="Flickr Accounts" />

            <PageShell>
                <PageShellIdentity
                    breadcrumbs={[flickrRootCrumb()]}
                    title="Flickr Accounts"
                    subtitle="Run crawls and transfers for connected accounts."
                />

                <PageShellCanvas className="space-y-6" variant="plain">
                {accounts.length === 0 ? (
                    <p className="text-sm text-slate-600">
                        No Flickr accounts connected yet.{' '}
                        <Link href="/settings?tab=flickr" className="text-cyan-700 hover:underline">
                            Connect in Settings
                        </Link>
                    </p>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {accounts.map((account) => (
                            <AccountOpsCard
                                key={account.public_id}
                                account={account}
                                summary={summaries[account.nsid] ?? null}
                            />
                        ))}
                    </div>
                )}
                </PageShellCanvas>
            </PageShell>
        </AppLayout>
    );
}
