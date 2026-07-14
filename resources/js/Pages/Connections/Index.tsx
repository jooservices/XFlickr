import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

import Button from '@/Components/Button';
import type { FlickrAppSummary } from '@/Components/Flickr/FlickrAppProfileCard';
import FlickrAppsPanel from '@/Components/Flickr/FlickrAppsPanel';
import { PageShell, PageShellCanvas, PageShellControlBar, PageShellIdentity } from '@/Components/layout/page-shell';
import AccountOpsCard from '@/Components/macros/AccountOpsCard';
import OnboardingWizard from '@/Components/Settings/OnboardingWizard';
import StorageCredentialsPanel from '@/Components/Settings/StorageCredentialsPanel';
import AppLayout from '@/Layouts/AppLayout';
import { connectionsRootCrumb } from '@/lib/breadcrumbs';
import { connectionsPath, type ConnectionsProvider } from '@/lib/connections';
import type { FlickrAccountSummary, PageProps, StorageAccount, StorageDriver } from '@/types';

interface StorageAppSummary {
    provider: string;
    label: string;
    client_id_hint: string;
    redirect: string | null;
    accounts_count: number;
}

interface Props extends PageProps {
    provider: ConnectionsProvider;
    flickr_accounts: FlickrAccountSummary[];
    flickr_apps: FlickrAppSummary[];
    default_callback_url: string;
    storage_accounts: StorageAccount[];
    storage_apps: StorageAppSummary[];
    storage_redirects: Record<string, string>;
    storage_drivers: StorageDriver[];
}

const providers = [
    { key: 'flickr', label: 'Flickr' },
    { key: 'storage', label: 'Storage' },
] as const;

export default function ConnectionsIndex({
    provider,
    flickr_accounts,
    flickr_apps,
    default_callback_url,
    storage_accounts,
    storage_apps,
    storage_redirects,
    storage_drivers,
}: Props) {
    const [openAddRequest, setOpenAddRequest] = useState(0);

    const setProvider = (next: ConnectionsProvider) => {
        router.get(connectionsPath({ provider: next }), {}, { preserveState: true, replace: true });
    };

    const startFlickrOAuth = (profile?: string) => {
        const appProfile = profile ?? flickr_apps[0]?.profile ?? 'main';
        window.location.href = `/flickr/oauth?app_profile=${encodeURIComponent(appProfile)}`;
    };

    const reconnectFlickrAccount = (account: FlickrAccountSummary) => {
        if (flickr_apps.length === 0) {
            setOpenAddRequest((value) => value + 1);

            return;
        }

        startFlickrOAuth(account.app_profile ?? flickr_apps[0]?.profile ?? 'main');
    };

    const handleConnect = () => {
        if (provider === 'storage') {
            setOpenAddRequest((value) => value + 1);

            return;
        }

        if (flickr_apps.length === 0) {
            setOpenAddRequest((value) => value + 1);

            return;
        }

        startFlickrOAuth();
    };

    const handleAddCredentials = () => {
        setOpenAddRequest((value) => value + 1);
    };

    return (
        <AppLayout>
            <Head title="Connections" />

            <PageShell>
                <PageShellIdentity
                    breadcrumbs={[connectionsRootCrumb()]}
                    title="Connections"
                    subtitle="Connect Flickr sources and storage destinations. Add credentials and authorize in one place."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button type="button" variant="secondary" onClick={handleAddCredentials}>
                                <Plus className="h-4 w-4" />
                                {provider === 'flickr' ? 'Add credentials' : 'Add destination'}
                            </Button>
                            {provider === 'flickr' && flickr_apps.length > 0 ? (
                                <Button type="button" variant="primary" onClick={handleConnect}>
                                    Connect
                                </Button>
                            ) : null}
                        </div>
                    }
                />

                <PageShellControlBar
                    tabs={
                        <div className="flex gap-1">
                            {providers.map((item) => (
                                <button
                                    key={item.key}
                                    type="button"
                                    onClick={() => setProvider(item.key)}
                                    className={`border-b-2 px-4 py-2 text-sm font-medium ${
                                        provider === item.key
                                            ? 'border-slate-900 text-slate-900'
                                            : 'border-transparent text-slate-500 hover:text-slate-700'
                                    }`}
                                >
                                    {item.label}
                                </button>
                            ))}
                        </div>
                    }
                />

                <PageShellCanvas className="space-y-6" variant="plain">
                    <OnboardingWizard
                        hasFlickrAccounts={flickr_accounts.length > 0}
                        hasStorageAccounts={storage_accounts.length > 0}
                    />

                    {provider === 'flickr' ? (
                        <div className="space-y-8">
                            <section className="space-y-4">
                                <div>
                                    <h2 className="text-lg font-semibold text-slate-900">Flickr accounts</h2>
                                    <p className="mt-1 text-sm text-slate-600">
                                        Connected accounts you can crawl, download, and upload from.
                                    </p>
                                </div>

                                {flickr_accounts.length === 0 ? (
                                    <p className="text-sm text-slate-600">
                                        No Flickr accounts connected yet.{' '}
                                        {flickr_apps.length === 0 ? (
                                            <>
                                                Add API credentials first, then connect.{' '}
                                                <button
                                                    type="button"
                                                    className="text-cyan-700 hover:underline"
                                                    onClick={handleAddCredentials}
                                                >
                                                    Add credentials
                                                </button>
                                            </>
                                        ) : (
                                            <button
                                                type="button"
                                                className="text-cyan-700 hover:underline"
                                                onClick={handleConnect}
                                            >
                                                Connect an account
                                            </button>
                                        )}
                                    </p>
                                ) : (
                                    <div className="grid gap-4 lg:grid-cols-2">
                                        {flickr_accounts.map((account) => (
                                            <AccountOpsCard
                                                key={account.public_id}
                                                account={account}
                                                onReconnect={() => reconnectFlickrAccount(account)}
                                            />
                                        ))}
                                    </div>
                                )}
                            </section>

                            <FlickrAppsPanel
                                apps={flickr_apps}
                                defaultCallbackUrl={default_callback_url}
                                openAddRequest={openAddRequest}
                                showHeading
                            />
                        </div>
                    ) : null}

                    {provider === 'storage' ? (
                        <StorageCredentialsPanel
                            apps={storage_apps}
                            accounts={storage_accounts}
                            redirects={storage_redirects}
                            drivers={storage_drivers}
                            openAddRequest={openAddRequest}
                        />
                    ) : null}
                </PageShellCanvas>
            </PageShell>
        </AppLayout>
    );
}
