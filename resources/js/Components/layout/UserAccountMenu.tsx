import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, LogOut, User } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { cn } from '@/lib/cn';
import type { PageProps } from '@/types';

type AuthUser = NonNullable<PageProps['auth']['user']>;

type UserAccountMenuProps = {
    user: AuthUser;
};

function userInitials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) {
        return '?';
    }
    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0] ?? ''}${parts[1][0] ?? ''}`.toUpperCase();
}

export default function UserAccountMenu({ user }: UserAccountMenuProps) {
    const { url } = usePage();
    const path = url.split('?')[0] ?? '';
    const [open, setOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        function onPointerDown(event: MouseEvent) {
            if (!menuRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        function onKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('mousedown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    useEffect(() => {
        setOpen(false);
    }, [path]);

    function logout() {
        setOpen(false);
        router.post('/logout');
    }

    return (
        <div ref={menuRef} className="relative">
            <button
                type="button"
                className={cn(
                    'flex h-9 max-w-[12rem] items-center gap-2 rounded-md px-2 text-sm font-medium sm:max-w-[14rem] sm:px-3',
                    open ? 'bg-slate-100 text-slate-900' : 'text-slate-700 hover:bg-slate-100',
                )}
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                aria-haspopup="menu"
                aria-label="Account menu"
            >
                <span
                    className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-xs font-semibold text-cyan-800"
                    aria-hidden
                >
                    {userInitials(user.name)}
                </span>
                <span className="hidden min-w-0 flex-col text-left sm:flex">
                    <span className="truncate font-medium text-slate-900">{user.name}</span>
                    {user.email ? <span className="truncate text-xs text-slate-500">{user.email}</span> : null}
                </span>
                <ChevronDown className={cn('h-4 w-4 shrink-0 text-slate-500 transition', open ? 'rotate-180' : '')} />
            </button>

            {open ? (
                <div
                    role="menu"
                    className="absolute right-0 z-40 mt-2 w-48 overflow-hidden rounded-md border border-slate-200 bg-white py-1 shadow-lg"
                >
                    <Link
                        href="/profile"
                        role="menuitem"
                        className={cn(
                            'flex items-center gap-2 px-3 py-2 text-sm',
                            path.startsWith('/profile')
                                ? 'bg-cyan-50 font-medium text-cyan-800'
                                : 'text-slate-700 hover:bg-slate-100',
                        )}
                        onClick={() => setOpen(false)}
                    >
                        <User className="h-4 w-4" />
                        Profile
                    </Link>
                    <div className="my-1 border-t border-slate-200" />
                    <button
                        type="button"
                        role="menuitem"
                        className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100"
                        onClick={logout}
                    >
                        <LogOut className="h-4 w-4" />
                        Sign out
                    </button>
                </div>
            ) : null}
        </div>
    );
}
