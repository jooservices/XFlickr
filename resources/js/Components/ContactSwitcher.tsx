import { router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useState } from 'react';

import { ActionButton } from '@/Components/ActionBar';
import SearchField from '@/Components/SearchField';
import { contactDisplayName, useContactSuggestions } from '@/hooks/useContactSuggestions';
import { cn } from '@/lib/cn';
import { flickrAccountPath, flickrContactPath } from '@/lib/flickrAccount';

export interface ContactSwitcherProps {
    accountPublicId: string;
    currentContactNsid: string;
    currentLabel?: string | null;
}

export default function ContactSwitcher({
    accountPublicId,
    currentContactNsid,
    currentLabel,
}: ContactSwitcherProps) {
    const [search, setSearch] = useState('');
    const { suggestions, loading } = useContactSuggestions(accountPublicId, search);

    const menu = (
        <div className="py-1">
            <div className="border-b border-slate-100 px-3 py-2">
                <SearchField
                    size="sm"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search contacts…"
                    containerClassName="w-full"
                    onClick={(event) => event.stopPropagation()}
                />
                <p className="mt-1.5 text-xs text-slate-500">Type at least 2 characters</p>
            </div>

            {loading ? (
                <p className="px-3 py-2 text-sm text-slate-500">Searching…</p>
            ) : search.trim().length >= 2 && suggestions.length === 0 ? (
                <p className="px-3 py-2 text-sm text-slate-500">No contacts found.</p>
            ) : (
                <ul className="max-h-64 overflow-y-auto">
                    {suggestions.map((contact) => {
                        const isCurrent = contact.nsid === currentContactNsid;

                        return (
                            <li key={contact.nsid}>
                                <button
                                    type="button"
                                    disabled={isCurrent}
                                    onClick={() => {
                                        router.get(flickrContactPath(accountPublicId, contact.nsid));
                                    }}
                                    className={cn(
                                        'flex w-full flex-col px-3 py-2 text-left text-sm',
                                        isCurrent
                                            ? 'cursor-default bg-slate-50 text-slate-500'
                                            : 'text-slate-700 hover:bg-slate-50',
                                    )}
                                >
                                    <span className="font-medium">{contactDisplayName(contact)}</span>
                                    <span className="text-xs text-slate-500">@{contact.username ?? contact.nsid}</span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}

            <div className="border-t border-slate-100 px-3 py-2">
                <a
                    href={flickrAccountPath(accountPublicId, '/contacts')}
                    className="text-xs font-medium text-blue-600 hover:underline"
                >
                    View all contacts
                </a>
            </div>
        </div>
    );

    return (
        <ActionButton
            label={currentLabel ?? 'Contacts'}
            icon={<Users className="size-3.5" />}
            menu={menu}
            menuMinWidth="min-w-72"
            alignMenu="left"
            size="md"
        />
    );
}
