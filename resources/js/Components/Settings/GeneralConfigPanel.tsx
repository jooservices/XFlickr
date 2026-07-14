import { router } from '@inertiajs/react';
import {
    ConfigPanel,
    type ConfigEntry,
    type ConfigRecord,
    type ConfigValueType,
} from '@jooservices/react-config';
import { Activity, DatabaseZap, Globe2, LayoutTemplate, Network } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import type { CuratedConfigEntry, CustomConfigEntry } from '@/types';

interface GeneralConfigPanelProps {
    curated: CuratedConfigEntry[];
    custom: CustomConfigEntry[];
    runtimeConfigAvailable: boolean;
    onOpenCreateReady?: (openCreate: (() => void) | null) => void;
}

const SETTINGS_TABS = [
    {
        id: 'operations',
        label: 'Operations',
        icon: Activity,
        filter: (entry: ConfigEntry) => entry.meta?.section === 'operations',
    },
    {
        id: 'crawl',
        label: 'Crawl',
        icon: Globe2,
        filter: (entry: ConfigEntry) => entry.meta?.section === 'crawl',
    },
    {
        id: 'discovery',
        label: 'Discovery',
        icon: Network,
        filter: (entry: ConfigEntry) => entry.meta?.section === 'discovery',
    },
    {
        id: 'application',
        label: 'Application',
        icon: LayoutTemplate,
        filter: (entry: ConfigEntry) => entry.meta?.section === 'application',
    },
    { id: 'raw', label: 'Custom', icon: DatabaseZap, mode: 'raw' as const },
];

function toConfigEntry(entry: CuratedConfigEntry): ConfigEntry {
    return {
        path: entry.path,
        label: entry.label,
        description: entry.description,
        group_label: entry.group_label,
        type: entry.type,
        tier: entry.tier,
        sort: entry.sort,
        effective_value: entry.effective_value,
        source: entry.source,
        stored: entry.stored,
        meta: {
            section: entry.section,
            is_core: entry.is_core,
        },
    };
}

function toConfigRecord(entry: CustomConfigEntry): ConfigRecord {
    return {
        id: entry.id ?? entry.path,
        path: entry.path,
        type: entry.type,
        value: entry.value,
    };
}

function serializeValue(value: unknown): string {
    if (typeof value === 'boolean') {
        return value ? 'true' : 'false';
    }

    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function inertiaMutation(
    method: 'post' | 'delete',
    url: string,
    data?: Record<string, string>,
): Promise<void> {
    return new Promise((resolve, reject) => {
        router.visit(url, {
            method,
            data,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: (errors) => {
                const first = Object.values(errors)[0];
                reject(new Error(typeof first === 'string' ? first : 'Request failed.'));
            },
        });
    });
}

function OpenCreateBridge({
    openCreate,
    onReady,
}: {
    openCreate: () => void;
    onReady?: (openCreate: (() => void) | null) => void;
}) {
    useEffect(() => {
        onReady?.(openCreate);

        return () => onReady?.(null);
    }, [openCreate, onReady]);

    return null;
}

export default function GeneralConfigPanel({
    curated,
    custom,
    runtimeConfigAvailable,
    onOpenCreateReady,
}: GeneralConfigPanelProps) {
    const [tab, setTab] = useState('operations');
    const [query, setQuery] = useState('');
    const [showExpert, setShowExpert] = useState(false);
    const [showTechnicalKeys, setShowTechnicalKeys] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const entries = useMemo(() => curated.map(toConfigEntry), [curated]);
    const rawEntries = useMemo(() => custom.map(toConfigRecord), [custom]);
    const corePaths = useMemo(() => new Set(curated.map((entry) => entry.path)), [curated]);

    useEffect(() => {
        if (!runtimeConfigAvailable) {
            onOpenCreateReady?.(null);
        }
    }, [runtimeConfigAvailable, onOpenCreateReady]);

    if (!runtimeConfigAvailable) {
        return (
            <p className="text-sm text-slate-600">
                Runtime config store is not available. Start MongoDB and ensure laravel-config is configured.
            </p>
        );
    }

    return (
        <ConfigPanel
            // Hide ConfigPanel's empty action row — New lives in PageShellIdentity actions.
            className="[&>div:first-child]:hidden"
            tabs={SETTINGS_TABS}
            generalTab={false}
            features={{ create: false }}
            entries={entries}
            rawEntries={rawEntries}
            valueTypes={['string', 'int', 'float', 'bool', 'array', 'json', 'null']}
            tab={tab}
            onTabChange={setTab}
            query={query}
            onQueryChange={setQuery}
            showExpert={showExpert}
            onShowExpertChange={setShowExpert}
            showTechnicalKeys={showTechnicalKeys}
            onShowTechnicalKeysChange={setShowTechnicalKeys}
            processing={processing}
            errors={errors}
            searchPlaceholder="Search settings by name, description, or key"
            headerActions={({ openCreate }) => (
                <OpenCreateBridge openCreate={openCreate} onReady={onOpenCreateReady} />
            )}
            onSave={async ({ path, type, value }) => {
                setProcessing(true);
                setErrors({});

                try {
                    await inertiaMutation('post', '/settings/config', {
                        path,
                        type: type as ConfigValueType,
                        value: serializeValue(value),
                    });
                } catch (error) {
                    setErrors({
                        value: error instanceof Error ? error.message : 'Failed to save configuration.',
                    });
                    throw error;
                } finally {
                    setProcessing(false);
                }
            }}
            onDelete={async (path) => {
                setProcessing(true);
                setErrors({});

                try {
                    if (corePaths.has(path)) {
                        await inertiaMutation('post', `/settings/config/${encodeURIComponent(path)}/reset`);
                    } else {
                        await inertiaMutation('delete', `/settings/config/${encodeURIComponent(path)}`);
                    }
                } catch (error) {
                    setErrors({
                        value: error instanceof Error ? error.message : 'Failed to remove configuration.',
                    });
                    throw error;
                } finally {
                    setProcessing(false);
                }
            }}
        />
    );
}
