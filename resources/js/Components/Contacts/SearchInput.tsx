import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import SearchField from '@/Components/ui/SearchField';
import {
    contactDisplayName,
    useContactSuggestions,
    type ContactSuggestion,
} from '@/hooks/useContactSuggestions';
import { flickrContactPath } from '@/lib/flickrAccount';

interface ContactSearchInputProps {
    accountPublicId: string;
    value: string;
    onChange: (value: string) => void;
}

export default function ContactSearchInput({ accountPublicId, value, onChange }: ContactSearchInputProps) {
    const { suggestions, loading } = useContactSuggestions(accountPublicId, value);
    const [open, setOpen] = useState(false);
    const [highlighted, setHighlighted] = useState(-1);
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (value.trim().length < 2) {
            setOpen(false);
            return;
        }

        if (!loading) {
            setOpen(suggestions.length > 0);
            setHighlighted(-1);
        }
    }, [value, suggestions, loading]);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const selectSuggestion = (suggestion: ContactSuggestion) => {
        setOpen(false);
        router.visit(flickrContactPath(accountPublicId, suggestion.nsid));
    };

    const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (!open || suggestions.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setHighlighted((prev) => (prev + 1) % suggestions.length);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setHighlighted((prev) => (prev <= 0 ? suggestions.length - 1 : prev - 1));
        } else if (event.key === 'Enter' && highlighted >= 0) {
            event.preventDefault();
            selectSuggestion(suggestions[highlighted]);
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    };

    return (
        <div ref={containerRef} className="relative min-w-64 flex-1">
            <SearchField
                role="combobox"
                aria-expanded={open}
                aria-autocomplete="list"
                autoComplete="off"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                onFocus={() => suggestions.length > 0 && setOpen(true)}
                onKeyDown={handleKeyDown}
                placeholder="Search by name or username…"
                containerClassName="w-full"
            />

            {open && suggestions.length > 0 && (
                <ul
                    role="listbox"
                    className="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg"
                >
                    {suggestions.map((suggestion, index) => (
                        <li key={suggestion.nsid} role="option" aria-selected={index === highlighted}>
                            <button
                                type="button"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => selectSuggestion(suggestion)}
                                onMouseEnter={() => setHighlighted(index)}
                                className={`w-full px-3 py-2 text-left text-sm ${
                                    index === highlighted ? 'bg-cyan-50 text-cyan-900' : 'text-slate-700 hover:bg-slate-50'
                                }`}
                            >
                                <span className="font-medium">{contactDisplayName(suggestion)}</span>
                                <span className="ml-2 text-xs text-slate-500">
                                    @{suggestion.username ?? suggestion.nsid}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
