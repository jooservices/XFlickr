export type OperationsPanel = 'overview' | 'crawl' | 'transfers';

export const OPERATIONS_PANELS: Array<{ id: OperationsPanel; label: string }> = [
    { id: 'overview', label: 'Overview' },
    { id: 'crawl', label: 'Crawl' },
    { id: 'transfers', label: 'Transfers' },
];

const VALID_PANELS = new Set<OperationsPanel>(OPERATIONS_PANELS.map((entry) => entry.id));

export function panelFromParam(value: string | null | undefined): OperationsPanel {
    if (value && VALID_PANELS.has(value as OperationsPanel)) {
        return value as OperationsPanel;
    }

    return 'overview';
}

export function readOperationsPanelFromLocation(): OperationsPanel {
    if (typeof window === 'undefined') {
        return 'overview';
    }

    return panelFromParam(new URLSearchParams(window.location.search).get('panel'));
}

export function writeOperationsPanelToLocation(panel: OperationsPanel): void {
    if (typeof window === 'undefined') {
        return;
    }

    const params = new URLSearchParams(window.location.search);
    if (panel === 'overview') {
        params.delete('panel');
    } else {
        params.set('panel', panel);
    }

    const query = params.toString();
    const next = query === '' ? window.location.pathname : `${window.location.pathname}?${query}`;
    window.history.replaceState({}, '', next);
}
