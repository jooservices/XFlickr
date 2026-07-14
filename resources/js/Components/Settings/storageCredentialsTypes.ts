export interface StorageAppSummary {
    provider: string;
    label: string;
    client_id_hint: string;
    redirect: string | null;
    accounts_count: number;
}

export interface StorageDriverOption {
    value: string;
    label: string;
    requires_oauth: boolean;
    requires_app: boolean;
    requires_account: boolean;
}

export interface StorageCredentialsPanelProps {
    apps: StorageAppSummary[];
    accounts: import('@/types').StorageAccount[];
    redirects: Record<string, string>;
    drivers: StorageDriverOption[];
    openAddRequest?: number;
}
