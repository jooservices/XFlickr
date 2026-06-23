import { Download, Heart, Images, Layers, LayoutGrid, Play, Upload } from 'lucide-react';

import { cn } from '@/lib/cn';
import { CRAWL_TYPE_OPTIONS, type ContactCatalogType } from '@/lib/contactCatalog';
import type { CrawlType } from '@/types';

const crawlIcons = {
    photos: Images,
    favorites: Heart,
    photosets: Layers,
    galleries: LayoutGrid,
} as const;

interface CrawlTypeMenuProps {
    onSelect: (types: CrawlType[]) => void;
    disabledTypes?: Partial<Record<CrawlType, boolean>>;
}

export default function CrawlTypeMenu({ onSelect, disabledTypes = {} }: CrawlTypeMenuProps) {
    const allTypes = CRAWL_TYPE_OPTIONS.map((option) => option.value);
    const availableTypes = allTypes.filter((type) => !disabledTypes[type]);
    const allDisabled = availableTypes.length === 0;

    return (
        <div className="py-1">
            <button
                type="button"
                disabled={allDisabled}
                onClick={() => onSelect(availableTypes)}
                className={cn(
                    'flex w-full gap-2 px-3 py-2 text-left text-sm',
                    allDisabled
                        ? 'cursor-not-allowed opacity-60'
                        : 'text-slate-900 hover:bg-slate-50',
                )}
            >
                <Layers className="mt-0.5 size-4 shrink-0 text-slate-400" />
                <span>
                    <span className="block font-medium">All</span>
                    <span className="block text-xs text-slate-500">Run all catalog crawls</span>
                </span>
            </button>
            {CRAWL_TYPE_OPTIONS.map((option) => {
                const type = option.value as ContactCatalogType;
                const Icon = crawlIcons[type];
                const disabled = disabledTypes[option.value] ?? false;

                return (
                    <button
                        key={option.value}
                        type="button"
                        disabled={disabled}
                        onClick={() => onSelect([option.value])}
                        className={cn(
                            'flex w-full gap-2 px-3 py-2 text-left',
                            disabled
                                ? 'cursor-not-allowed opacity-60'
                                : 'text-slate-700 hover:bg-slate-50',
                        )}
                    >
                        <Icon className="mt-0.5 size-4 shrink-0 text-slate-400" />
                        <span className="min-w-0">
                            <span className="block text-sm font-medium">{option.label}</span>
                            <span className="block text-xs text-slate-500">{option.description}</span>
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

export function bulkCrawlActionIcon() {
    return <Play className="size-3" />;
}

export function bulkDownloadActionIcon() {
    return <Download className="size-3" />;
}

export function bulkUploadActionIcon() {
    return <Upload className="size-3" />;
}
