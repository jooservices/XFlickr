import { useEffect, useState } from 'react';

import { apiGet } from '@/lib/apiClient';
import { flickrApiAccountPath } from '@/lib/flickrAccount';

export interface ContactSuggestion {
    nsid: string;
    username: string | null;
    realname: string | null;
}

interface UseContactSuggestionsOptions {
    debounceMs?: number;
    minLength?: number;
}

export function useContactSuggestions(
    accountPublicId: string,
    search: string,
    { debounceMs = 200, minLength = 2 }: UseContactSuggestionsOptions = {},
) {
    const [suggestions, setSuggestions] = useState<ContactSuggestion[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const trimmed = search.trim();

        if (trimmed.length < minLength) {
            setSuggestions([]);
            setLoading(false);
            return;
        }

        const controller = new AbortController();
        setLoading(true);

        const timeout = setTimeout(() => {
            void apiGet<ContactSuggestion[]>(flickrApiAccountPath(accountPublicId, '/contacts/suggest'), {
                params: { search: trimmed },
                signal: controller.signal,
            })
                .then((data) => {
                    setSuggestions(data);
                })
                .catch(() => {
                    if (!controller.signal.aborted) {
                        setSuggestions([]);
                    }
                })
                .finally(() => {
                    if (!controller.signal.aborted) {
                        setLoading(false);
                    }
                });
        }, debounceMs);

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
    }, [search, accountPublicId, debounceMs, minLength]);

    return { suggestions, loading };
}

export function contactDisplayName(contact: ContactSuggestion): string {
    return contact.realname || contact.username || contact.nsid;
}
