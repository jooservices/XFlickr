import { APP_BOTTOM_RAIL_FOOTER_CLASS } from '@/Components/Layout/appBottomRail';
import NavbarRateLimit from '@/Components/Layout/NavbarRateLimit';
import StorageQuotaMeter from '@/Components/Storage/QuotaMeter';
import { storageAccountLabel } from '@/hooks/useStorageQuota';
import { cn } from '@/lib/cn';
import type { FlickrRateLimitSnapshot, RateLimitState, StorageQuotaAccountSummary, StorageQuotaState } from '@/types';

interface AppStatusFooterProps {
    appName: string;
    flickr: {
        snapshot: FlickrRateLimitSnapshot | null;
        selectedNsid: string | null;
        setSelectedNsid: (nsid: string) => void;
        selectedRateLimit: RateLimitState | null;
        loading: boolean;
    };
    storage: {
        accounts: StorageQuotaAccountSummary[];
        selectedAccountId: number | null;
        setSelectedAccountId: (accountId: number) => void;
        selectedRow: StorageQuotaAccountSummary | null;
        selectedQuota: StorageQuotaState | null;
        loading: boolean;
    };
}

export default function AppStatusFooter({ appName, flickr, storage }: AppStatusFooterProps) {
    const year = new Date().getFullYear();
    const showStoragePicker = storage.accounts.length > 1;
    const storageStatus = storage.selectedRow?.status;
    const storageMessage = storage.selectedRow?.message;

    return (
        <footer
            className={cn(APP_BOTTOM_RAIL_FOOTER_CLASS, 'justify-between gap-3 px-4 sm:px-6')}
            aria-label="Application status"
        >
            <p className="shrink-0 truncate text-xs text-slate-500">
                © {year} {appName}
            </p>

            <div className="flex min-w-0 flex-nowrap items-center justify-end gap-3 overflow-hidden">
                <NavbarRateLimit
                    snapshot={flickr.snapshot}
                    selectedNsid={flickr.selectedNsid}
                    setSelectedNsid={flickr.setSelectedNsid}
                    selectedRateLimit={flickr.selectedRateLimit}
                    loading={flickr.loading}
                />

                {storage.accounts.length === 0 ? (
                    <p className="shrink-0 truncate text-xs text-slate-400">No storage accounts</p>
                ) : (
                    <div
                        className={cn('flex min-w-0 items-center gap-2', storage.loading && 'opacity-80')}
                        aria-live="polite"
                        aria-busy={storage.loading}
                    >
                        {showStoragePicker ? (
                            <select
                                value={storage.selectedAccountId ?? ''}
                                onChange={(event) => storage.setSelectedAccountId(Number(event.target.value))}
                                className="max-w-[8rem] truncate rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-700"
                                aria-label="Storage account for quota"
                            >
                                {storage.accounts.map((row) => (
                                    <option key={row.account.id} value={row.account.id}>
                                        {storageAccountLabel(row.account)}
                                    </option>
                                ))}
                            </select>
                        ) : null}

                        {storage.selectedQuota ? (
                            <StorageQuotaMeter quota={storage.selectedQuota} label="Storage" variant="footer" />
                        ) : (
                            <p
                                className="max-w-[12rem] truncate text-xs text-slate-400"
                                title={storageMessage ?? undefined}
                            >
                                {storageStatus === 'error'
                                    ? 'Storage quota unavailable'
                                    : storageStatus === 'unsupported'
                                      ? 'Storage quota n/a'
                                      : 'Loading storage…'}
                            </p>
                        )}
                    </div>
                )}
            </div>
        </footer>
    );
}
