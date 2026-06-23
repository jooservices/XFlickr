import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, RotateCcw, Trash2, X } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

import type { ConfigValueType, CuratedConfigEntry, CustomConfigEntry } from '@/types';

interface GeneralConfigPanelProps {
    curated: CuratedConfigEntry[];
    custom: CustomConfigEntry[];
    runtimeConfigAvailable: boolean;
}

function formatValue(type: ConfigValueType, value: unknown): string {
    if (type === 'bool') {
        return value ? 'Yes' : 'No';
    }

    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function valueToInput(type: ConfigValueType, value: unknown): string {
    if (type === 'bool') {
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

export default function GeneralConfigPanel({
    curated,
    custom,
    runtimeConfigAvailable,
}: GeneralConfigPanelProps) {
    const [editing, setEditing] = useState<CuratedConfigEntry | CustomConfigEntry | null>(null);
    const [addingCustom, setAddingCustom] = useState(false);

    const form = useForm({
        path: '',
        type: 'string' as ConfigValueType,
        value: '',
    });

    const grouped = useMemo(() => {
        const groups = new Map<string, CuratedConfigEntry[]>();

        for (const entry of curated) {
            const list = groups.get(entry.group) ?? [];
            list.push(entry);
            groups.set(entry.group, list);
        }

        return Array.from(groups.entries());
    }, [curated]);

    const openEdit = (entry: CuratedConfigEntry | CustomConfigEntry) => {
        form.setData({
            path: entry.path,
            type: entry.type,
            value: valueToInput(
                entry.type,
                'effective_value' in entry ? entry.effective_value : entry.value,
            ),
        });
        form.clearErrors();
        setEditing(entry);
        setAddingCustom(false);
    };

    const openAddCustom = () => {
        form.setData({ path: '', type: 'string', value: '' });
        form.clearErrors();
        setEditing(null);
        setAddingCustom(true);
    };

    const closeDialog = () => {
        setEditing(null);
        setAddingCustom(false);
        form.reset();
        form.clearErrors();
    };

    const save = (event: FormEvent) => {
        event.preventDefault();
        form.post('/settings/config', {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        });
    };

    const resetConfig = (path: string) => {
        router.post(`/settings/config/${encodeURIComponent(path)}/reset`, {}, { preserveScroll: true });
    };

    const deleteConfig = (path: string) => {
        router.delete(`/settings/config/${encodeURIComponent(path)}`, { preserveScroll: true });
    };

    if (!runtimeConfigAvailable) {
        return (
            <p className="text-sm text-slate-600">
                Runtime config store is not available. Start MongoDB and ensure laravel-config is configured.
            </p>
        );
    }

    return (
        <div className="space-y-8">
            {grouped.map(([group, entries]) => (
                <section key={group}>
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{group}</h2>
                    <div className="mt-3 divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                        {entries.map((entry) => (
                            <div
                                key={entry.path}
                                className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium text-slate-900">{entry.label}</p>
                                    <p className="text-xs text-slate-500">{entry.path}</p>
                                    <p className="mt-1 text-sm text-slate-700">
                                        {formatValue(entry.type, entry.effective_value)}
                                        <span className="ml-2 text-xs text-slate-400">
                                            ({entry.source === 'default' ? 'default' : 'stored'})
                                        </span>
                                    </p>
                                </div>
                                <div className="flex shrink-0 gap-2">
                                    <button
                                        type="button"
                                        onClick={() => openEdit(entry)}
                                        className="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium hover:bg-slate-50"
                                    >
                                        <Pencil className="size-3.5" />
                                        Edit
                                    </button>
                                    {entry.stored ? (
                                        <button
                                            type="button"
                                            onClick={() => resetConfig(entry.path)}
                                            className="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium hover:bg-slate-50"
                                        >
                                            <RotateCcw className="size-3.5" />
                                            Reset
                                        </button>
                                    ) : null}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            ))}

            {custom.length > 0 ? (
                <section>
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Custom</h2>
                    <div className="mt-3 divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                        {custom.map((entry) => (
                            <div
                                key={entry.path}
                                className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium text-slate-900">{entry.path}</p>
                                    <p className="mt-1 text-sm text-slate-700">{formatValue(entry.type, entry.value)}</p>
                                </div>
                                <div className="flex shrink-0 gap-2">
                                    <button
                                        type="button"
                                        onClick={() => openEdit(entry)}
                                        className="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium hover:bg-slate-50"
                                    >
                                        <Pencil className="size-3.5" />
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => deleteConfig(entry.path)}
                                        className="inline-flex items-center gap-1 rounded-md border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                                    >
                                        <Trash2 className="size-3.5" />
                                        Delete
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            ) : null}

            <div>
                <button
                    type="button"
                    onClick={openAddCustom}
                    className="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium shadow-sm hover:bg-slate-50"
                >
                    <Plus className="size-4" />
                    Add custom config
                </button>
            </div>

            {(editing || addingCustom) ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
                    <div className="w-full max-w-lg rounded-lg border border-slate-200 bg-white p-6 shadow-xl">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-slate-900">
                                {addingCustom ? 'Add custom config' : 'Edit config'}
                            </h3>
                            <button
                                type="button"
                                onClick={closeDialog}
                                className="rounded-md p-1 text-slate-500 hover:bg-slate-100"
                                aria-label="Close"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form className="space-y-4" onSubmit={save}>
                            <label className="block text-sm">
                                <span className="text-slate-600">Path (group.key)</span>
                                <input
                                    value={form.data.path}
                                    onChange={(event) => form.setData('path', event.target.value)}
                                    required
                                    readOnly={!addingCustom}
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 read-only:bg-slate-50"
                                    placeholder="xflickr.example_key"
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">Type</span>
                                <select
                                    value={form.data.type}
                                    onChange={(event) =>
                                        form.setData('type', event.target.value as ConfigValueType)
                                    }
                                    disabled={!addingCustom && editing !== null && 'is_core' in editing && editing.is_core}
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 disabled:bg-slate-50"
                                >
                                    {(['string', 'int', 'float', 'bool', 'array', 'json', 'null'] as const).map(
                                        (type) => (
                                            <option key={type} value={type}>
                                                {type}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">Value</span>
                                {form.data.type === 'bool' ? (
                                    <select
                                        value={form.data.value}
                                        onChange={(event) => form.setData('value', event.target.value)}
                                        className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    >
                                        <option value="true">true</option>
                                        <option value="false">false</option>
                                    </select>
                                ) : (
                                    <input
                                        value={form.data.value}
                                        onChange={(event) => form.setData('value', event.target.value)}
                                        className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    />
                                )}
                            </label>

                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={closeDialog}
                                    className="rounded-md border border-slate-200 px-4 py-2 text-sm"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-md bg-cyan-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                                >
                                    {form.processing ? 'Saving…' : 'Save'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
