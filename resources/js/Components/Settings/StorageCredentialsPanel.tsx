import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useMemo, useState } from 'react';

import type { StorageCredentialsPanelProps } from '@/Components/Settings/storageCredentialsTypes';
import StorageDestinationCards from '@/Components/Settings/StorageDestinationCards';
import StorageDestinationPickerModal from '@/Components/Settings/StorageDestinationPickerModal';
import StorageOAuthAppModal from '@/Components/Settings/StorageOAuthAppModal';
import StorageR2ConnectModal from '@/Components/Settings/StorageR2ConnectModal';
import { connectionsPath } from '@/lib/connections';

const connectionsReturnUrl = connectionsPath({ provider: 'storage' });

export default function StorageCredentialsPanel({
    apps,
    accounts,
    redirects,
    drivers,
    openAddRequest = 0,
}: StorageCredentialsPanelProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [r2DialogOpen, setR2DialogOpen] = useState(false);
    const [pickerOpen, setPickerOpen] = useState(false);

    const form = useForm({
        provider: 'google_photos',
        label: '',
        client_id: '',
        client_secret: '',
        redirect: redirects.google_photos ?? '',
    });

    const r2Form = useForm({
        label: '',
        access_key_id: '',
        secret_access_key: '',
        bucket: '',
        endpoint: '',
        region: 'auto',
        prefix: '',
    });

    const oauthApps = apps.filter((app) => app.provider !== 'r2');
    const configuredOAuthProviders = useMemo(
        () => new Set(oauthApps.map((app) => app.provider)),
        [oauthApps],
    );
    const accountsNeedingReauth = accounts.filter((account) => account.needs_reauthorization);
    const r2Accounts = accounts.filter((account) => account.provider === 'r2');
    const oauthAccounts = accounts.filter((account) => account.provider !== 'r2');

    const providerLabel = (value: string) => drivers.find((driver) => driver.value === value)?.label ?? value;

    const openCreate = (provider: string) => {
        setPickerOpen(false);
        form.setData({
            provider,
            label: '',
            client_id: '',
            client_secret: '',
            redirect: redirects[provider] ?? '',
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const openAddR2 = () => {
        setPickerOpen(false);
        r2Form.clearErrors();
        r2Form.reset();
        setR2DialogOpen(true);
    };

    useEffect(() => {
        if (openAddRequest <= 0) {
            return;
        }

        setPickerOpen(true);
    }, [openAddRequest]);

    const saveApp = (event: FormEvent) => {
        event.preventDefault();
        form.post('/settings/storage-app', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setDialogOpen(false);
            },
        });
    };

    const connectR2 = (event: FormEvent) => {
        event.preventDefault();
        r2Form.post('/storage/connect/r2', {
            preserveScroll: true,
            onSuccess: () => {
                r2Form.reset();
                setR2DialogOpen(false);
            },
        });
    };

    const startOAuth = (provider: string) => {
        window.location.href = `/storage/oauth/${encodeURIComponent(provider)}?return_url=${encodeURIComponent(connectionsReturnUrl)}`;
    };

    const hasAnyAccounts = oauthAccounts.length > 0 || r2Accounts.length > 0;
    const unconnectedApps = oauthApps.filter(
        (app) => !accounts.some((account) => account.provider === app.provider),
    );

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-semibold text-slate-900">Storage destinations</h2>
                <p className="mt-1 text-sm text-slate-600">
                    Connected upload targets. Add credentials and connect OAuth or R2 from{' '}
                    <span className="font-medium">Add destination</span>.
                </p>
            </div>

            <StorageDestinationCards
                oauthApps={oauthApps}
                oauthAccounts={oauthAccounts}
                r2Accounts={r2Accounts}
                unconnectedApps={unconnectedApps}
                accountsNeedingReauth={accountsNeedingReauth}
                hasAnyAccounts={hasAnyAccounts}
                connectionsReturnUrl={connectionsReturnUrl}
                providerLabel={providerLabel}
                onOpenPicker={() => setPickerOpen(true)}
                onConnectOAuth={startOAuth}
            />

            <StorageDestinationPickerModal
                open={pickerOpen}
                onClose={() => setPickerOpen(false)}
                configuredOAuthProviders={configuredOAuthProviders}
                onCreateOAuth={openCreate}
                onConnectOAuth={startOAuth}
                onAddR2={openAddR2}
            />

            <StorageOAuthAppModal
                open={dialogOpen}
                onClose={() => setDialogOpen(false)}
                form={form}
                providerLabel={providerLabel}
                onSubmit={saveApp}
            />

            <StorageR2ConnectModal
                open={r2DialogOpen}
                onClose={() => setR2DialogOpen(false)}
                form={r2Form}
                onSubmit={connectR2}
            />
        </div>
    );
}
