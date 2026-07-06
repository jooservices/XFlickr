export type CrawlType = 'contacts' | 'photos' | 'photosets' | 'galleries' | 'favorites';

export interface CrawlTypeState {
    processing: boolean;
    crawled: boolean;
    fetched?: number;
    total?: number | null;
}

export type ContactCrawlState = Record<Exclude<CrawlType, 'contacts'>, CrawlTypeState>;

export interface FlickrAccount {
    public_id: string;
    nsid: string;
    username: string | null;
    fullname: string | null;
    app_profile: string | null;
    connected_at: string | null;
    is_active: boolean;
    disconnected_at?: string | null;
}

export interface FlickrAccountSummary {
    public_id: string;
    nsid: string;
    username: string | null;
    fullname: string | null;
    app_profile: string | null;
    connected_at: string | null;
    is_active: boolean;
    is_connected: boolean;
    disconnected_at: string | null;
}

export interface FlickrOAuthStatus {
    connected: boolean;
    account: FlickrAccountSummary | null;
}

export interface FlickrSettings {
    default_app_profile: string;
    global_pause: boolean;
}

export interface StorageAccount {
    id: number;
    provider: string;
    label: string;
    is_default: boolean;
    connected_at: string | null;
    needs_reauthorization: boolean;
    missing_scopes: StorageScopeDefinition[];
    reauthorize_url: string;
    connection_meta: StorageConnectionMeta | null;
}

export interface StorageConnectionMeta {
    bucket?: string | null;
    endpoint?: string | null;
    prefix?: string | null;
}

export interface StorageScopeDefinition {
    scope: string;
    label: string;
}

export interface RemoteStorageAlbum {
    id: string;
    title: string;
    cover_thumbnail_url: string | null;
    media_items_count: number | null;
}

export interface RemoteStorageItem {
    id: string;
    name: string;
    mime_type: string | null;
    thumbnail_url: string | null;
    size: number | null;
    modified_at: string | null;
    path: string | null;
    web_url: string | null;
}

export interface StorageBrowseMeta {
    per_page: number;
    album_next_page_token: string | null;
    item_next_page_token: string | null;
    has_more_albums: boolean;
    has_more_items: boolean;
    source?: 'local' | 'provider';
    album_page?: number;
    album_last_page?: number;
    album_total?: number;
    item_page?: number;
    item_last_page?: number;
    item_total?: number;
    last_synced_at?: string | null;
    sync_has_more?: boolean;
}

export interface StorageDriver {
    value: string;
    label: string;
    requires_oauth: boolean;
    requires_app: boolean;
    requires_account: boolean;
}

export interface ContactListItem {
    nsid: string;
    username: string | null;
    realname: string | null;
    photos_count: number;
    favorites_count: number;
    photosets_count: number;
    galleries_count: number;
    downloads_count: number;
    downloads_failed_count: number;
    download_state?: ContactDownloadState;
    crawl_state?: ContactCrawlState;
}

export interface ContactDownloadState {
    processing: boolean;
    batch_completed?: number;
    batch_total?: number;
}

export interface Contact {
    nsid: string;
    username: string | null;
    realname: string | null;
    friend: boolean;
    family: boolean;
    raw_payload?: Record<string, unknown> | null;
}

export interface CatalogStatBlock {
    db: number;
    with_sizes?: number;
    in_api: number | null;
}

export interface ContactCatalogStats {
    photos: CatalogStatBlock;
    photosets: { db: number; in_api: number | null };
    favorites: { db: number; in_api: number | null };
    galleries: { db: number; in_api: number | null };
}

export interface Favorite {
    id: number;
    connection_key: string;
    subject_nsid: string;
    xflickr_photo_id: number;
    photo_owner_nsid: string | null;
    discovered_at: string | null;
    photo?: Photo | null;
}

export interface PaginatedMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    sort?: string;
    direction?: 'asc' | 'desc';
}

export interface CrawlRun {
    id: number;
    connection_key: string;
    crawl_type: string;
    subject_nsid: string | null;
    status: string;
    contacts_discovered: number;
    photos_discovered: number;
    api_calls: number;
    started_at: string | null;
    completed_at: string | null;
    failed_reason: string | null;
}

export interface TransferBatch {
    id: number;
    type: 'download' | 'upload';
    connection_key: string;
    subject_nsid: string | null;
    group_type: 'owner' | 'photoset' | 'gallery' | null;
    group_id: string | null;
    group_label: string | null;
    storage_account_id: number | null;
    status: string;
    total_count: number;
    completed_count: number;
    failed_count: number;
    created_at: string | null;
    updated_at: string | null;
}

export interface RateLimitState {
    requests_used: number;
    max_requests_per_hour: number;
    requests_remaining: number;
    window_seconds: number;
    window_reset_at: string | null;
    window_seconds_remaining: number;
    global_pause: boolean;
    cooldown_until: string | null;
    cooldown_seconds_remaining: number;
}

export interface ApiUsageBucket {
    hour_start: string;
    requests: number;
    is_current: boolean;
}

export interface FlickrApiUsageSnapshot {
    connection_key: string;
    hours: number;
    generated_at: string;
    max_requests_per_hour: number;
    buckets: ApiUsageBucket[];
    rate_limit: RateLimitState;
}

export interface FlickrRateLimitSnapshot {
    generated_at: string;
    active_connection_key: string | null;
    accounts: Array<{
        account: FlickrAccount;
        rate_limit: RateLimitState;
    }>;
}

export interface CrawlSummary {
    connection_key: string;
    runs: {
        running: number;
        completed: number;
        failed: number;
    };
    pending_targets: number;
    global_pause: boolean;
    rate_limit: RateLimitState;
}

export interface DashboardSnapshotAccountRow {
    account: FlickrAccount;
    rate_limit: RateLimitState;
    runs: {
        running: number;
        completed: number;
        failed: number;
    };
    pending_targets: number;
    contacts_db: number;
    photos_db: number;
    photos_with_sizes: number;
    photosets_db: number;
    galleries_db: number;
    latest_run: CrawlRun | null;
    transfers: {
        downloads_active: number;
        uploads_active: number;
        failed_items_24h: number;
    };
}

export interface DashboardSnapshot {
    generated_at: string;
    global: {
        accounts: number;
        runs_running: number;
        pending_targets: number;
        stored_files: number;
        downloads_active: number;
        uploads_active: number;
        failed_transfers_24h: number;
    };
    accounts: DashboardSnapshotAccountRow[];
    alerts: {
        any_cooldown: boolean;
    };
}

export interface PhotoMembership {
    flickr_id: string;
    owner_nsid: string;
    title: string | null;
}

export interface Photo {
    id: number;
    flickr_photo_id: string;
    owner_nsid: string;
    title: string | null;
    secret: string | null;
    server: string | null;
    farm: number | null;
    photosets?: PhotoMembership[];
    galleries?: PhotoMembership[];
}

export interface Photoset {
    id: number;
    flickr_photoset_id: string;
    owner_nsid: string;
    title: string | null;
    photo_count: number | null;
    primary_photo_id?: string | null;
    primary_secret?: string | null;
    primary_server?: string | null;
}

export interface Gallery {
    id: number;
    flickr_gallery_id: string;
    owner_nsid: string;
    title: string | null;
    photo_count: number | null;
    primary_photo_id?: string | null;
    primary_secret?: string | null;
    primary_server?: string | null;
}

export interface PageProps {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
        } | null;
    };
    app: {
        name: string;
        global_pause?: boolean;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
    [key: string]: unknown;
}

export type ConfigValueType = 'string' | 'int' | 'float' | 'bool' | 'array' | 'json' | 'null';

export interface CuratedConfigEntry {
    path: string;
    label: string;
    type: ConfigValueType;
    default: unknown;
    group: string;
    is_core: boolean;
    sort: number;
    effective_value: unknown;
    stored: boolean;
    source: 'config-store' | 'default';
}

export interface CustomConfigEntry {
    path: string;
    type: ConfigValueType;
    value: unknown;
    is_core: boolean;
}

export interface RuntimeConfigPayload {
    curated: CuratedConfigEntry[];
    custom: CustomConfigEntry[];
}
