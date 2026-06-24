import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Camera,
    Cloud,
    HardDrive,
    Heart,
    Images,
    LayoutDashboard,
    Layers,
    Menu,
    Server,
    Settings,
    Users,
    X,
} from 'lucide-react';
import { type PropsWithChildren, useState } from 'react';

import NavbarRateLimit from '@/Components/NavbarRateLimit';
import { cn } from '@/lib/cn';
import type { PageProps } from '@/types';

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
    },
    {
        href: '/photos',
        label: 'Photos',
        icon: Images,
        isActive: isPhotosPath,
    },
    {
        href: '/photosets',
        label: 'Photosets',
        icon: Layers,
        isActive: isPhotosetsPath,
    },
    {
        href: '/favorites',
        label: 'Favorites',
        icon: Heart,
        isActive: isFavoritesPath,
    },
    {
        href: '/galleries',
        label: 'Galleries',
        icon: Images,
        isActive: isGalleriesPath,
    },
] as const;

const SIDEBAR_WIDTH = 'lg:w-56';

const topNav = [
    { href: '/crawl/operations', label: 'Operations', icon: Activity },
    {
        href: '/flickr/accounts',
        label: 'Flickr',
        icon: Camera,
        isActive: (path: string) => path.startsWith('/flickr/accounts') && !path.includes('/contacts'),
    },
    { href: '/settings', label: 'Settings', icon: Settings },
] as const;

export default function AppLayout({ children }: PropsWithChildren) {
    const { props, url } = usePage<PageProps>();
    const { app, flash } = props;
    const globalPause = app.global_pause ?? false;
    const [mobileOpen, setMobileOpen] = useState(false);

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            {globalPause ? (
                <div className="border-b border-rose-200 bg-rose-50 px-4 py-2 text-center text-sm font-medium text-rose-900">
                    Global crawl pause is active — jobs will not dispatch until resumed in Settings.
                </div>
            ) : null}

            <header className="sticky top-0 z-30 border-b border-slate-200 bg-white">
                <div className="flex min-h-14 items-center lg:divide-x lg:divide-slate-200">
                    <div
                        className={cn(
                            'flex items-center gap-3 px-4 lg:shrink-0 lg:px-6',
                            SIDEBAR_WIDTH,
                        )}
                    >
                        <button
                            type="button"
                            className="rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
                            onClick={() => setMobileOpen((open) => !open)}
                            aria-label="Toggle navigation"
                        >
                            {mobileOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                        </button>

                        <Link href="/dashboard" className="flex items-center gap-2">
                            <Camera className="h-5 w-5 text-cyan-700" />
                            <span className="font-semibold text-slate-900">{app.name}</span>
                        </Link>
                    </div>

                    <div className="flex min-w-0 flex-1 items-center justify-between gap-3 px-3 py-2 md:gap-4 md:px-4 lg:px-6">
                        <nav className="hidden items-center gap-1 md:flex">
                            {topNav.map((item) => {
                                const path = url.split('?')[0] ?? '';
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
                        </nav>

                        <NavbarRateLimit />
                    </div>
                </div>
            </header>

            {(flash.success || flash.error) && (
                <div className="border-b border-slate-200 bg-white px-4 py-3 lg:px-6">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}
                    {flash.error && (
                        <div className="mt-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 first:mt-0">
                            {flash.error}
                        </div>
                    )}
                </div>
            )}

            <div className="lg:flex">
                <aside
                    className={cn(
                        'border-b border-slate-200 bg-white lg:sticky lg:top-14 lg:max-h-[calc(100vh-3.5rem)] lg:overflow-y-auto lg:self-start lg:border-b-0 lg:border-r',
                        SIDEBAR_WIDTH,
                        mobileOpen ? 'block' : 'hidden lg:block',
                    )}
                >
                    <nav className="flex flex-col gap-1 p-3">
                        {sidebarNav.map((item) => {
                            const path = url.split('?')[0] ?? '';
                            const active =
                                'isActive' in item && item.isActive
                                    ? item.isActive(path)
                                    : path === item.href || path.startsWith(`${item.href}/`);
                            const Icon = item.icon;

                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    onClick={() => setMobileOpen(false)}
                                    className={cn(
                                        'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium',
                                        active ? 'bg-cyan-50 text-cyan-800' : 'text-slate-600 hover:bg-slate-100',
                                    )}
                                >
                                    <Icon className="h-4 w-4" />
                                    {item.label}
                                </Link>
                            );
                        })}

                        <div className="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Storages
                        </div>

                        {storageNav.map((item) => {
                            const path = url.split('?')[0] ?? '';
                            const active =
                                'isActive' in item && item.isActive
                                    ? item.isActive(path)
                                    : path === item.href || path.startsWith(`${item.href}/`);
                            const Icon = item.icon;

                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    onClick={() => setMobileOpen(false)}
                                    className={cn(
                                        'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium',
                                        active ? 'bg-cyan-50 text-cyan-800' : 'text-slate-600 hover:bg-slate-100',
                                    )}
                                >
                                    <Icon className="h-4 w-4" />
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                <main className="flex-1 p-6">{children}</main>
            </div>
        </div>
    );
}
