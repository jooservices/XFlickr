export type CommandPaletteFilterable = {
    id: string;
    label: string;
    keywords?: string[];
};

function haystackFor(item: CommandPaletteFilterable): string {
    const parts = [item.label, ...(item.keywords ?? [])];

    return parts.join(' ').toLowerCase();
}

/**
 * Case-insensitive substring filter. Empty / whitespace query returns all items in order.
 */
export function filterCommandPaletteItems<T extends CommandPaletteFilterable>(
    items: T[],
    query: string,
): T[] {
    const needle = query.trim().toLowerCase();
    if (needle === '') {
        return items;
    }

    return items.filter((item) => haystackFor(item).includes(needle));
}

export function shouldIgnoreCommandPaletteToggle(event: KeyboardEvent): boolean {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    // Allow Cmd/Ctrl+K from most places; only skip contenteditable rich editors.
    return target.isContentEditable;
}

export function isCommandPaletteToggleEvent(event: KeyboardEvent): boolean {
    if (event.key !== 'k' && event.key !== 'K') {
        return false;
    }

    return event.metaKey || event.ctrlKey;
}
