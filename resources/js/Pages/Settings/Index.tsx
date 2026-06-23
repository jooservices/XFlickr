import { Head, router } from '@inertiajs/react';

import Breadcrumbs from '@/Components/Breadcrumbs';
import PageHeading from '@/Components/PageHeading';
import FlickrCredentialsPanel from '@/Components/Settings/FlickrCredentialsPanel';
import GeneralConfigPanel from '@/Components/Settings/GeneralConfigPanel';
import StorageCredentialsPanel from '@/Components/Settings/StorageCredentialsPanel';
import AppLayout from '@/Layouts/AppLayout';
import { settingsCrumbs } from '@/lib/breadcrumbs';
import type {
    FlickrAccountSummary,
    PageProps,
    RuntimeConfigPayload,
    StorageAccount,
    StorageDriver,
} from '@/types';

interface FlickrAppSummary {
    profile: string;
    label: string | null;
    api_key_hint: string;
    callback_url: string | null;
    accounts_count: number;
}

interface FlickrSettingsData {
    status: {
        connected: boolean;
        account: {
            id: number;
            nsid: string;
            username: string | null;
            fullname: string | null;
            is_active: boolean;
        } | null;
    };
    accounts: FlickrAccountSummary[];
    apps: FlickrAppSummary[];
    default_callback_url: string;
    default_app_profile: string;
    settings: {
        default_app_profile: string;
        global_pause: boolean;
    };
}

interface StorageAppSummary {
    provider: string;
    label: string;
    client_id_hint: string;
    redirect: string | null;
    accounts_count: number;
}

interface Props extends PageProps {
    tab: 'general' | 'flickr' | 'storage';
    runtime_config: RuntimeConfigPayload;
    flickr: FlickrSettingsData;
    storage_accounts: StorageAccount[];
    storage_apps: StorageAppSummary[];
    storage_redirects: Record<string, string>;
    storage_drivers: StorageDriver[];
    runtime_config_available: boolean;
}

const tabs = [
    { key: 'general', label: 'General' },
    { key: 'flickr', label: 'Flickr' },
    { key: 'storage', label: 'Storages' },
] as const;

export default function SettingsIndex({
    tab,
    runtime_config,
    flickr,
    storage_accounts,
    storage_apps,
    storage_redirects,
    storage_drivers,
    runtime_config_available,
}: Props) {
    const setTab = (next: string) => {
        router.get('/settings', { tab: next }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title="Settings" />

            <div className="space-y-6">
                <PageHeading
                    breadcrumbs={<Breadcrumbs items={settingsCrumbs(tab)} />}
                    title="Settings"
                    subtitle="Configure runtime options, manage connections, and storage credentials."
                />

                <div className="flex gap-1 border-b border-slate-200">
                    {tabs.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => setTab(item.key)}
                            className={`border-b-2 px-4 py-2 text-sm font-medium ${
                                tab === item.key
                                    ? 'border-slate-900 text-slate-900'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
                            }`}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                {tab === 'general' && (
                    <GeneralConfigPanel
                        curated={runtime_config.curated}
                        custom={runtime_config.custom}
                        runtimeConfigAvailable={runtime_config_available}
                    />
                )}

                {tab === 'flickr' && (
                    <FlickrCredentialsPanel
                        accounts={flickr.accounts}
                        apps={flickr.apps}
                        default_callback_url={flickr.default_callback_url}
                    />
                )}

                {tab === 'storage' && (
                    <StorageCredentialsPanel
                        apps={storage_apps}
                        accounts={storage_accounts}
                        redirects={storage_redirects}
                        drivers={storage_drivers}
                    />
                )}
            </div>
        </AppLayout>
    );
}
