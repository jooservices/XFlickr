import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent as ReactKeyboardEvent } from 'react';

import Button from '@/Components/ui/Button';
import LoadingIndicator from '@/Components/ui/LoadingIndicator';
import Modal from '@/Components/ui/Modal';
import SearchField from '@/Components/ui/SearchField';
import {
    contactDisplayName,
    useContactSuggestions,
    type ContactSuggestion,
} from '@/hooks/useContactSuggestions';
import { cn } from '@/lib/cn';
import {
    commandPaletteNavigationItems,
    type CommandPaletteNavItem,
} from '@/lib/commandPaletteCatalog';
import {
    filterCommandPaletteItems,
    isCommandPaletteToggleEvent,
    shouldIgnoreCommandPaletteToggle,
} from '@/lib/commandPaletteFilter';
import { flickrContactPath } from '@/lib/flickrAccount';

type PaletteRow =
    | { kind: 'nav'; item: CommandPaletteNavItem }
    | { kind: 'contact'; suggestion: ContactSuggestion; href: string };

interface CommandPaletteProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    accountPublicId: string | null;
}

export default function CommandPalette({ open, onOpenChange, accountPublicId }: CommandPaletteProps) {
    const titleId = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);

    const navItems = useMemo(() => commandPaletteNavigationItems(), []);
    const filteredNav = useMemo(() => filterCommandPaletteItems(navItems, query), [navItems, query]);

    const { suggestions, loading: contactsLoading } = useContactSuggestions(
        accountPublicId ?? '',
        accountPublicId ? query : '',
        { minLength: 2 },
    );

    const rows: PaletteRow[] = useMemo(() => {
        const next: PaletteRow[] = filteredNav.map((item) => ({ kind: 'nav', item }));

        if (accountPublicId) {
            for (const suggestion of suggestions) {
                next.push({
                    kind: 'contact',
                    suggestion,
                    href: flickrContactPath(accountPublicId, suggestion.nsid),
                });
            }
        }

        return next;
    }, [filteredNav, suggestions, accountPublicId]);

    useEffect(() => {
        if (!open) {
            return;
        }

        setQuery('');
        setActiveIndex(0);
        const frame = window.requestAnimationFrame(() => {
            inputRef.current?.focus();
        });

        return () => window.cancelAnimationFrame(frame);
    }, [open]);

    useEffect(() => {
        setActiveIndex(0);
    }, [query]);

    useEffect(() => {
        if (activeIndex >= rows.length && rows.length > 0) {
            setActiveIndex(rows.length - 1);
        }
    }, [activeIndex, rows.length]);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (!isCommandPaletteToggleEvent(event)) {
                return;
            }

            if (shouldIgnoreCommandPaletteToggle(event)) {
                return;
            }

            event.preventDefault();
            onOpenChange(!open);
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [open, onOpenChange]);

    const close = () => onOpenChange(false);

    const runRow = (row: PaletteRow) => {
        const href = row.kind === 'nav' ? row.item.href : row.href;
        close();
        router.visit(href);
    };

    const onListKeyDown = (event: ReactKeyboardEvent) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveIndex((index) => (rows.length === 0 ? 0 : (index + 1) % rows.length));
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((index) =>
                rows.length === 0 ? 0 : (index - 1 + rows.length) % rows.length,
            );
            return;
        }

        if (event.key === 'Enter') {
            const row = rows[activeIndex];
            if (row) {
                event.preventDefault();
                runRow(row);
            }
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
    };

    return (
        <Modal open={open} onClose={close} size="lg" titleId={titleId} zIndexClassName="z-[120]">
            <Modal.Header title="Jump to…" showClose />
            <div className="border-b border-slate-200 px-4 py-3" onKeyDown={onListKeyDown}>
                <SearchField
                    ref={inputRef}
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Search pages or contacts…"
                    aria-label="Command palette search"
                    autoComplete="off"
                />
            </div>
            <Modal.Body className="!py-2">
                <div onKeyDown={onListKeyDown}>
                    {rows.length === 0 ? (
                        <p className="px-1 py-6 text-center text-sm text-slate-500">
                            {contactsLoading ? 'Searching contacts…' : 'No matches.'}
                        </p>
                    ) : (
                        <ul className="space-y-1" role="listbox" aria-label="Command palette results">
                            {rows.map((row, index) => {
                                const active = index === activeIndex;
                                const key =
                                    row.kind === 'nav'
                                        ? row.item.id
                                        : `contact-${row.suggestion.nsid}`;
                                const label =
                                    row.kind === 'nav'
                                        ? row.item.label
                                        : contactDisplayName(row.suggestion);
                                const meta =
                                    row.kind === 'nav' ? row.item.section : row.suggestion.nsid;

                                return (
                                    <li key={key}>
                                        <button
                                            type="button"
                                            role="option"
                                            aria-selected={active}
                                            className={cn(
                                                'flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm',
                                                active
                                                    ? 'bg-cyan-50 text-cyan-900'
                                                    : 'text-slate-700 hover:bg-slate-50',
                                            )}
                                            onMouseEnter={() => setActiveIndex(index)}
                                            onClick={() => runRow(row)}
                                        >
                                            <span className="min-w-0 truncate font-medium">
                                                {label}
                                            </span>
                                            <span className="shrink-0 font-mono text-xs text-slate-400">
                                                {meta}
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    {accountPublicId && contactsLoading ? (
                        <div className="mt-2 flex justify-center">
                            <LoadingIndicator size="sm" label="Searching contacts" />
                        </div>
                    ) : null}
                </div>
            </Modal.Body>
            <Modal.Footer className="!justify-between text-xs text-slate-500">
                <span>↑↓ navigate · Enter open · Esc close</span>
                <span className="font-mono">⌘K / Ctrl+K</span>
            </Modal.Footer>
        </Modal>
    );
}

export function CommandPaletteTrigger({ onClick }: { onClick: () => void }) {
    return (
        <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={onClick}
            className="hidden items-center gap-2 sm:inline-flex"
            aria-label="Open command palette"
            title="Jump to (⌘K)"
        >
            <Search className="size-3.5" aria-hidden />
            <span className="text-slate-500">Jump to</span>
            <kbd className="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-mono text-[10px] text-slate-500">
                ⌘K
            </kbd>
        </Button>
    );
}
