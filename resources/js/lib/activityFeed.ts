export type ActivityLogType = 'domain' | 'audit' | 'system' | 'security' | 'activity';

export type ActivityLogLevel =
    | 'debug'
    | 'info'
    | 'notice'
    | 'warning'
    | 'error'
    | 'critical'
    | 'alert'
    | 'emergency';

export type ActivityFeedIdentity = {
    type: string;
    id: string | null;
};

export type ActivityFeedEntry = {
    id: string;
    type: ActivityLogType | string;
    level: ActivityLogLevel | string | null;
    action: string;
    message: string | null;
    actor: ActivityFeedIdentity | null;
    subject: ActivityFeedIdentity | null;
    correlation_id: string | null;
    trace_id: string | null;
    properties: Record<string, unknown>;
    context: Record<string, unknown>;
    changes: Record<string, unknown>;
    occurred_at: string | null;
};

export type ActivityFeedFacets = {
    by_level: Record<string, number>;
};

export type ActivityFeedMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    facets: ActivityFeedFacets;
};

export type ActivityFeedFilters = {
    type: string;
    level: string;
    action_prefix: string;
    correlation_id: string;
    from: string;
    to: string;
    page: number;
    per_page: number;
};

export type ActivityFeedRange = '24h' | '7d' | '30d' | 'all';
