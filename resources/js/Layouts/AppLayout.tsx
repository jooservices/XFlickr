import { Link, usePage } from '@inertiajs/react';
import { AppShell, useAppShell } from '@jooservices/react-layout';
import {
    Activity,
    Camera,
    Cloud,
    HardDrive,
    Heart,
    Images,
    LayoutDashboard,
    Layers,
    Link2,
    Server,
    Settings,
    Users,
} from 'lucide-react';
import { type PropsWithChildren, type ReactNode, useMemo, useState } from 'react';

import TokenHealthBanner from '@/Components/Flickr/TokenHealthBanner';
import { APP_SIDEBAR_FOOTER_RESET_CLASS } from '@/Components/layout/appBottomRail';
import AppSidebarFooter from '@/Components/layout/AppSidebarFooter';
import AppStatusFooter from '@/Components/layout/AppStatusFooter';
import GlobalCrawlPauseButton from '@/Components/layout/GlobalCrawlPauseButton';
import { SpiderModeButton } from '@/Components/layout/SpiderModeButton';
import UserAccountMenu from '@/Components/layout/UserAccountMenu';
import SidebarActivityPanel from '@/Components/SidebarActivityPanel';
import { useFlashToast } from '@/hooks/useFlashToast';
import { useFlickrRateLimit } from '@/hooks/useFlickrRateLimit';
import { useOperationsStream } from '@/hooks/useOperationsStream';
import { useStorageQuota } from '@/hooks/useStorageQuota';
import { cn } from '@/lib/cn';
import { connectionsPath } from '@/lib/connections';
import type { FlickrCatalogCounts, PageProps, SpiderSharedConfig } from '@/types';

function isContactsPath(path: string): boolean {
    return path === '/contacts' || path.startsWith('/contacts/') || /\/contacts(\/|$)/.test(path);
}

function isFavoritesPath(path: string): boolean {
    return path === '/favorites' || path.startsWith('/favorites/') || /\/favorites(\/|$)/.test(path);
}

function isPhotosPath(path: string): boolean {
    if (path.includes('photosets')) {
        return false;
    }

    if (path.includes('favorites')) {
        return false;
    }

    return path === '/photos' || path.startsWith('/photos/') || /\/photos(\/|$)/.test(path);
}

function isPhotosetsPath(path: string): boolean {
    return path === '/photosets' || path.startsWith('/photosets/') || /\/photosets(\/|$)/.test(path);
}

function isGalleriesPath(path: string): boolean {
    return path === '/galleries' || path.startsWith('/galleries/') || /\/galleries(\/|$)/.test(path);
}

function isStoragePath(path: string, slug: string): boolean {
    return path === `/storages/${slug}` || path.startsWith(`/storages/${slug}/`);
}

const defaultSpider: SpiderSharedConfig = {
    enabled: false,
    max_depth: 2,
    max_new_contacts_per_run: 25,
    max_contacts_total: 500,
};

const storageNav = [
    {
        href: '/storages/google-photos',
        label: 'Google Photos',
        icon: Images,
        isActive: (path: string) => isStoragePath(path, 'google-photos'),
    },
    {
        href: '/storages/google-drive',
        label: 'Google Drive',
        icon: HardDrive,
        isActive: (path: string) => isStoragePath(path, 'google-drive'),
    },
    {
        href: '/storages/onedrive',
        label: 'OneDrive',
        icon: Cloud,
        isActive: (path: string) => isStoragePath(path, 'onedrive'),
    },
    {
        href: '/storages/r2',
        label: 'Cloudflare R2',
        icon: Server,
        isActive: (path: string) => isStoragePath(path, 'r2'),
    },
] as const;

const sidebarNav = [
    { href: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
    {
        href: '/contacts',
        label: 'Contacts',
        icon: Users,
        isActive: isContactsPath,
        countKey: 'contacts_db' as const,
    },
    {
        href: '/photos',
        label: 'Photos',
        icon: Images,
        isActive: isPhotosPath,
        countKey: 'photos_db' as const,
    },
    {
        href: '/photosets',
        label: 'Photosets',
        icon: Layers,
        isActive: isPhotosetsPath,
        countKey: 'photosets_db' as const,
    },
    {
        href: '/favorites',
        label: 'Favorites',
        icon: Heart,
        isActive: isFavoritesPath,
        countKey: 'favorites_db' as const,
    },
    {
        href: '/galleries',
        label: 'Galleries',
        icon: Images,
        isActive: isGalleriesPath,
        countKey: 'galleries_db' as const,
    },
] as const;

type SidebarCountKey = keyof FlickrCatalogCounts;

function formatSidebarCount(value: number): string {
    return new Intl.NumberFormat().format(value);
}

function sidebarCountForItem(
    catalogCounts: FlickrCatalogCounts | null,
    countKey: SidebarCountKey | undefined,
): number | null {
    if (!catalogCounts || countKey === undefined) {
        return null;
    }

    return catalogCounts[countKey];
}

function stickySidebarOffsetClass({
    globalPause,
    tokenBannerVisible,
}: {
    globalPause: boolean;
    tokenBannerVisible: boolean;
}): string | undefined {
    const bannerCount = (globalPause ? 1 : 0) + (tokenBannerVisible ? 1 : 0);

    if (bannerCount === 0) {
        return undefined;
    }

    if (bannerCount === 1) {
        return 'lg:!top-[5.75rem] lg:!max-h-[calc(100vh-5.75rem)]';
    }

    return 'lg:!top-[8.25rem] lg:!max-h-[calc(100vh-8.25rem)]';
}

const topNav = [
    { href: '/operations', label: 'Operations', icon: Activity },
    {
        href: connectionsPath(),
        label: 'Connections',
        icon: Link2,
        isActive: (path: string) =>
            path.startsWith('/connections') ||
            (path.startsWith('/flickr/accounts') && !path.includes('/contacts')),
    },
    { href: '/settings', label: 'Settings', icon: Settings },
] as const;

function ShellNavLink({
    href,
    active,
    className,
    children,
}: {
    href: string;
    active: boolean;
    className?: string;
    children: ReactNode;
}) {
    const { setMobileOpen } = useAppShell();

    return (
        <Link
            href={href}
            onClick={() => setMobileOpen(false)}
            className={cn(
                'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium',
                active ? 'bg-cyan-50 text-cyan-800' : 'text-slate-600 hover:bg-slate-100',
                className,
            )}
        >
            {children}
        </Link>
    );
}

export default function AppLayout({ children }: PropsWithChildren) {
    const { props, url } = usePage<PageProps>();
    const { app, auth, flash } = props;
    const globalPause = app.global_pause ?? false;
    const [tokenBannerVisible, setTokenBannerVisible] = useState(false);
    const spider = app.spider ?? defaultSpider;
    const {
        snapshot: rateLimitSnapshot,
        selectedNsid,
        setSelectedNsid,
        selectedRateLimit,
        selectedCatalogCounts,
        loading: rateLimitLoading,
    } = useFlickrRateLimit();
    const {
        snapshot: storageQuotaSnapshot,
        selectedAccountId: storageQuotaAccountId,
        setSelectedAccountId: setStorageQuotaAccountId,
        selectedRow: storageQuotaRow,
        selectedQuota: storageQuota,
        loading: storageQuotaLoading,
    } = useStorageQuota();
    const { fetchRuns, downloadBatches, uploadBatches, loading: operationsLoading } = useOperationsStream();

    useFlashToast(flash);

    const accountByNsid = useMemo(
        () =>
            Object.fromEntries(
                (rateLimitSnapshot?.accounts ?? []).map((row) => [row.account.nsid, row.account]),
            ),
        [rateLimitSnapshot],
    );

    const connectedFlickrAccounts = useMemo(
        () => (rateLimitSnapshot?.accounts ?? []).map((row) => row.account),
        [rateLimitSnapshot],
    );

    const sidebarOffsetClass = stickySidebarOffsetClass({
        globalPause,
        tokenBannerVisible,
    });

    const path = url.split('?')[0] ?? '';

    return (
        <AppShell sidebarWidth="14rem">
            <div className="sticky top-0 z-40">
                {globalPause ? (
                    <div className="border-b border-rose-200 bg-rose-50 px-4 py-2 text-center text-sm font-medium text-rose-900">
                        Global crawl pause is active — jobs will not dispatch until resumed.
                    </div>
                ) : null}

                <TokenHealthBanner
                    accounts={connectedFlickrAccounts}
                    onVisibleChange={setTokenBannerVisible}
                />

                <AppShell.Header className="!static">
                    <AppShell.HeaderRow>
                        <AppShell.Brand>
                            <Link href="/dashboard" className="flex items-center gap-2">
                                <Camera className="h-5 w-5 text-cyan-700" />
                                <span className="font-semibold text-slate-900">{app.name}</span>
                            </Link>
                        </AppShell.Brand>

                        <AppShell.HeaderMain>
                            <AppShell.HeaderNav>
                                {topNav.map((item) => {
                                    const active =
                                        'isActive' in item && item.isActive
                                            ? item.isActive(path)
                                            : path.startsWith(item.href);
                                    const Icon = item.icon;

                                    return (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            className={cn(
                                                'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium',
                                                active
                                                    ? 'bg-cyan-50 text-cyan-800'
                                                    : 'text-slate-600 hover:bg-slate-100',
                                            )}
                                        >
                                            <Icon className="h-4 w-4" />
                                            {item.label}
                                        </Link>
                                    );
                                })}
                            </AppShell.HeaderNav>

                            <AppShell.HeaderActions>
                                <GlobalCrawlPauseButton paused={globalPause} />
                                <SpiderModeButton spider={spider} />
                                {auth.user ? <UserAccountMenu user={auth.user} /> : null}
                            </AppShell.HeaderActions>
                        </AppShell.HeaderMain>
                    </AppShell.HeaderRow>
                </AppShell.Header>
            </div>

            <AppShell.Body>
                <AppShell.Sidebar
                    className={cn(APP_SIDEBAR_FOOTER_RESET_CLASS, sidebarOffsetClass)}
                    footer={<AppSidebarFooter />}
                >
                    <div className="flex min-h-full flex-col">
                        <div className="flex flex-col gap-1 p-3">
                            {sidebarNav.map((item) => {
                                const active =
                                    'isActive' in item && item.isActive
                                        ? item.isActive(path)
                                        : path === item.href || path.startsWith(`${item.href}/`);
                                const Icon = item.icon;
                                const count = sidebarCountForItem(
                                    selectedCatalogCounts,
                                    'countKey' in item ? item.countKey : undefined,
                                );

                                return (
                                    <ShellNavLink key={item.href} href={item.href} active={active}>
                                        <Icon className="h-4 w-4 shrink-0" />
                                        <span className="truncate">{item.label}</span>
                                        {count !== null ? (
                                            <span
                                                className={cn(
                                                    'ml-auto tabular-nums text-xs',
                                                    active ? 'text-cyan-700' : 'text-slate-400',
                                                )}
                                            >
                                                {formatSidebarCount(count)}
                                            </span>
                                        ) : null}
                                    </ShellNavLink>
                                );
                            })}

                            <div className="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                Storages
                            </div>

                            {storageNav.map((item) => {
                                const active =
                                    'isActive' in item && item.isActive
                                        ? item.isActive(path)
                                        : path === item.href || path.startsWith(`${item.href}/`);
                                const Icon = item.icon;

                                return (
                                    <ShellNavLink key={item.href} href={item.href} active={active}>
                                        <Icon className="h-4 w-4" />
                                        {item.label}
                                    </ShellNavLink>
                                );
                            })}
                        </div>

                        <SidebarActivityPanel
                            className="mt-auto"
                            fetchRuns={fetchRuns}
                            downloadBatches={downloadBatches}
                            uploadBatches={uploadBatches}
                            loading={operationsLoading}
                            accountByNsid={accountByNsid}
                        />
                    </div>
                </AppShell.Sidebar>

                <AppShell.Main className="min-h-0">
                    <AppShell.MainFrame className="min-h-0 overflow-y-auto">{children}</AppShell.MainFrame>
                    <AppStatusFooter
                        appName={app.name}
                        flickr={{
                            snapshot: rateLimitSnapshot,
                            selectedNsid,
                            setSelectedNsid,
                            selectedRateLimit,
                            loading: rateLimitLoading,
                        }}
                        storage={{
                            accounts: storageQuotaSnapshot?.accounts ?? [],
                            selectedAccountId: storageQuotaAccountId,
                            setSelectedAccountId: setStorageQuotaAccountId,
                            selectedRow: storageQuotaRow,
                            selectedQuota: storageQuota,
                            loading: storageQuotaLoading,
                        }}
                    />
                </AppShell.Main>
            </AppShell.Body>
        </AppShell>
    );
}
