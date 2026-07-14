import { ChevronDown, Download, Heart, Images, Layers, LayoutGrid, Play, Upload, Users } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import CrawlActionMenuHeader from '@/Components/CrawlActionMenuHeader';
import { cn } from '@/lib/cn';
import {
    CONTACTS_DISCOVERY_CRAWL_OPTION,
    CRAWL_TYPE_OPTIONS,
    DOWNLOAD_OPTION,
    UPLOAD_OPTION,
    type CrawlTypeOption,
} from '@/lib/contactCatalog';
import type { CrawlSubjectLabel } from '@/lib/crawlSubject';
import type { CrawlType, CrawlTypeState } from '@/types';

export type { CrawlSubjectLabel };

interface CrawlDropdownProps {
    onCrawl?: (types: CrawlType[]) => void;
    onDownload?: () => void;
    onUpload?: () => void;
    exclude?: CrawlType[];
    includeContactsDiscovery?: boolean;
    showCrawlOptions?: boolean;
    crawlPaused?: boolean;
    typeStates?: Partial<Record<CrawlType, CrawlTypeState>>;
    subjectLabel?: CrawlSubjectLabel;
    label?: string;
    size?: 'sm' | 'md';
    variant?: 'default' | 'primary';
}

const crawlIcons: Record<CrawlType, typeof Users> = {
    contacts: Users,
    photos: Images,
    favorites: Heart,
    photosets: Layers,
    galleries: LayoutGrid,
};

function crawlFetchedLabel(state: CrawlTypeState | undefined): string | null {
    if (!state?.crawled || state.processing) {
        return null;
    }

    if (state.fetched === 0) {
        return 'Fetched · 0 in catalog';
    }

    return 'Already fetched · refetch';
}

function crawlStatusClass(state: CrawlTypeState | undefined, disabled: boolean): string {
    if (disabled || state?.processing) {
        return 'cursor-not-allowed opacity-60';
    }

    if (state?.crawled && state.fetched === 0) {
        return 'text-amber-800 hover:bg-amber-50';
    }

    if (state?.crawled) {
        return 'text-emerald-800 hover:bg-emerald-50';
    }

    return 'text-slate-700 hover:bg-slate-50';
}

export default function CrawlDropdown({
    onCrawl,
    onDownload,
    onUpload,
    exclude = [],
    includeContactsDiscovery = false,
    showCrawlOptions = true,
    crawlPaused = false,
    typeStates = {},
    subjectLabel,
    label = 'Crawl',
    size = 'sm',
    variant = 'default',
}: CrawlDropdownProps) {
    const [open, setOpen] = useState(false);
    const [menuPosition, setMenuPosition] = useState<{ top: number; left: number } | null>(null);
    const containerRef = useRef<HTMLDivElement>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    const options = CRAWL_TYPE_OPTIONS.filter((option) => !exclude.includes(option.value));
    const allTypes = options.map((option) => option.value);
    const availableTypes = allTypes.filter((type) => !typeStates[type]?.processing);
    const allProcessing = options.length > 0 && availableTypes.length === 0;
    const allCrawled = options.every((option) => typeStates[option.value]?.crawled);
    const allFetchedEmpty = allCrawled && options.every((option) => (typeStates[option.value]?.fetched ?? 0) === 0);

    const updateMenuPosition = () => {
        const button = buttonRef.current;
        const menu = menuRef.current;
        if (!button || !menu) {
            return;
        }

        const rect = button.getBoundingClientRect();
        const menuWidth = menu.offsetWidth;
        const menuHeight = menu.offsetHeight;
        const gap = 4;
        const padding = 8;

        let top = rect.bottom + gap;
        if (top + menuHeight > window.innerHeight - padding) {
            top = Math.max(padding, rect.top - menuHeight - gap);
        }

        let left = rect.right - menuWidth;
        left = Math.max(padding, Math.min(left, window.innerWidth - menuWidth - padding));

        setMenuPosition({ top, left });
    };

    useLayoutEffect(() => {
        if (!open) {
            setMenuPosition(null);
            return;
        }

        updateMenuPosition();
    }, [open, onDownload, onUpload, options.length, includeContactsDiscovery, showCrawlOptions, subjectLabel?.nsid]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const handleClickOutside = (event: MouseEvent) => {
            const target = event.target as Node;
            if (
                containerRef.current?.contains(target) ||
                menuRef.current?.contains(target)
            ) {
                return;
            }

            setOpen(false);
        };

        const handleReposition = () => updateMenuPosition();

        document.addEventListener('mousedown', handleClickOutside);
        window.addEventListener('resize', handleReposition);
        window.addEventListener('scroll', handleReposition, true);

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            window.removeEventListener('resize', handleReposition);
            window.removeEventListener('scroll', handleReposition, true);
        };
    }, [open]);

    const close = () => setOpen(false);

    const selectCrawl = (types: CrawlType[]) => {
        if (crawlPaused) {
            return;
        }

        const runnable = types.filter((type) => !typeStates[type]?.processing);
        if (runnable.length === 0) {
            return;
        }

        onCrawl?.(runnable);
        close();
    };

    const selectDownload = () => {
        onDownload?.();
        close();
    };

    const selectUpload = () => {
        onUpload?.();
        close();
    };

    const buttonClass =
        variant === 'primary'
            ? 'inline-flex items-center gap-1 rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800'
            : `inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white font-medium hover:bg-slate-50 ${
                  size === 'md' ? 'px-3 py-2 text-sm' : 'px-2.5 py-1.5 text-xs'
              }`;

    const renderMenuItem = (
        option: CrawlTypeOption,
        onClick: () => void,
        disabled = false,
        extraClass = '',
        iconOverride?: typeof Users,
    ) => {
        const Icon = iconOverride ?? crawlIcons[option.value];
        const state = typeStates[option.value];
        const isProcessing = state?.processing ?? false;
        const fetchedLabel = crawlFetchedLabel(state);

        return (
            <button
                key={option.value + option.label}
                type="button"
                disabled={disabled || isProcessing}
                onClick={onClick}
                className={cn(
                    'flex w-full gap-2 px-3 py-2 text-left',
                    crawlStatusClass(state, disabled),
                    extraClass,
                )}
            >
                <Icon className="mt-0.5 size-4 shrink-0 text-slate-400" />
                <span className="min-w-0">
                    <span className="block text-sm font-medium">{option.label}</span>
                    <span className="block text-xs text-slate-500">{option.description}</span>
                    {isProcessing ? (
                        <span className="mt-0.5 block text-xs text-slate-400">Processing…</span>
                    ) : fetchedLabel ? (
                        <span
                            className={cn(
                                'mt-0.5 block text-xs',
                                state?.fetched === 0 ? 'text-amber-600' : 'text-emerald-600',
                            )}
                        >
                            {fetchedLabel}
                        </span>
                    ) : null}
                </span>
            </button>
        );
    };

    const menu = open ? (
        <div
            ref={menuRef}
            style={
                menuPosition
                    ? { position: 'fixed', top: menuPosition.top, left: menuPosition.left }
                    : { position: 'fixed', top: -9999, left: -9999, visibility: 'hidden' as const }
            }
            className="z-50 min-w-52 overflow-hidden rounded-md border border-slate-200 bg-white shadow-lg"
        >
            {subjectLabel ? <CrawlActionMenuHeader subject={subjectLabel} /> : null}
            {crawlPaused && showCrawlOptions ? (
                <p className="border-b border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    Global crawl pause is active — resume from the header to start crawls.
                </p>
            ) : null}
            <div className="py-1">
            {showCrawlOptions && includeContactsDiscovery && !exclude.includes('contacts') && (
                <>
                    {renderMenuItem(CONTACTS_DISCOVERY_CRAWL_OPTION, () => selectCrawl(['contacts']), crawlPaused)}
                    <div className="my-1 border-t border-slate-100" />
                </>
            )}
            {showCrawlOptions && allTypes.length > 0 && (
                <button
                    type="button"
                    disabled={crawlPaused || allProcessing}
                    onClick={() => selectCrawl(availableTypes)}
                    className={cn(
                        'flex w-full gap-2 px-3 py-2 text-left',
                        crawlPaused || allProcessing
                            ? 'cursor-not-allowed opacity-60'
                            : allCrawled && allFetchedEmpty
                              ? 'text-amber-800 hover:bg-amber-50'
                              : allCrawled
                                ? 'text-emerald-800 hover:bg-emerald-50'
                                : 'text-slate-900 hover:bg-slate-50',
                    )}
                >
                    <Layers className="mt-0.5 size-4 shrink-0 text-slate-400" />
                    <span className="min-w-0">
                        <span className="block text-sm font-medium">All</span>
                        <span className="block text-xs text-slate-500">Run all catalog crawls</span>
                        {!allProcessing && allCrawled ? (
                            <span
                                className={cn(
                                    'mt-0.5 block text-xs',
                                    allFetchedEmpty ? 'text-amber-600' : 'text-emerald-600',
                                )}
                            >
                                {allFetchedEmpty ? 'Fetched · 0 in catalog' : 'Already fetched · refetch'}
                            </span>
                        ) : null}
                    </span>
                </button>
            )}
            {showCrawlOptions && options.map((option) => renderMenuItem(option, () => selectCrawl([option.value]), crawlPaused))}

            {onDownload && (
                <>
                    <div className="my-1 border-t border-slate-100" />
                    {renderMenuItem(
                        { value: 'photos', ...DOWNLOAD_OPTION },
                        selectDownload,
                        false,
                        '',
                        Download,
                    )}
                </>
            )}

            {onUpload && (
                <>
                    <div className="my-1 border-t border-slate-100" />
                    {renderMenuItem(
                        { value: 'photos', ...UPLOAD_OPTION },
                        selectUpload,
                        false,
                        '',
                        Upload,
                    )}
                </>
            )}
            </div>
        </div>
    ) : null;

    return (
        <div ref={containerRef} className="relative inline-block">
            <button
                ref={buttonRef}
                type="button"
                onClick={() => setOpen((value) => !value)}
                className={buttonClass}
            >
                <Play className={size === 'md' ? 'size-3.5' : 'size-3'} />
                {label}
                <ChevronDown className={size === 'md' ? 'size-3.5' : 'size-3'} />
            </button>

            {menu && createPortal(menu, document.body)}
        </div>
    );
}
