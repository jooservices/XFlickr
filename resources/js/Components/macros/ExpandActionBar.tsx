import { router } from '@inertiajs/react';
import { ChevronDown, Network, Users } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import ExpandConfirmModal, { type ExpandMode } from '@/Components/ExpandConfirmModal';
import { apiGet } from '@/lib/apiClient';
import { flickrAccountPath, flickrApiAccountPath } from '@/lib/flickrAccount';
import type { ExpandPreviewPayload } from '@/types';

interface ExpandActionBarProps {
    accountPublicId: string;
    size?: 'sm' | 'md';
}

export default function ExpandActionBar({ accountPublicId, size = 'sm' }: ExpandActionBarProps) {
    const [open, setOpen] = useState(false);
    const [modalMode, setModalMode] = useState<ExpandMode | null>(null);
    const [preview, setPreview] = useState<ExpandPreviewPayload | null>(null);
    const [loadingPreview, setLoadingPreview] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    const [menuPosition, setMenuPosition] = useState<{ top: number; left: number } | null>(null);

    const loadPreview = useCallback(async () => {
        setLoadingPreview(true);
        try {
            const data = await apiGet<{ data: ExpandPreviewPayload }>(
                flickrApiAccountPath(accountPublicId, '/expand-previews'),
            );
            setPreview(data.data);
        } catch {
            setPreview(null);
        } finally {
            setLoadingPreview(false);
        }
    }, [accountPublicId]);

    const openModal = (mode: ExpandMode) => {
        setOpen(false);
        setModalMode(mode);
        void loadPreview();
    };

    const closeModal = () => {
        if (submitting) {
            return;
        }

        setModalMode(null);
    };

    const confirmStart = () => {
        if (!modalMode) {
            return;
        }

        const path =
            modalMode === 'spider'
                ? flickrAccountPath(accountPublicId, '/spider/start')
                : flickrAccountPath(accountPublicId, '/full-pass/start');

        setSubmitting(true);
        router.post(path, {}, {
            preserveScroll: true,
            onFinish: () => {
                setSubmitting(false);
                setModalMode(null);
            },
        });
    };

    useEffect(() => {
        if (!open) {
            setMenuPosition(null);
            return;
        }

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
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const handleClickOutside = (event: MouseEvent) => {
            const target = event.target as Node;
            if (containerRef.current?.contains(target) || menuRef.current?.contains(target)) {
                return;
            }

            setOpen(false);
        };

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [open]);

    const buttonClass =
        size === 'md'
            ? 'inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50'
            : 'inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium hover:bg-slate-50';

    const menu = open ? (
        <div
            ref={menuRef}
            style={
                menuPosition
                    ? { position: 'fixed', top: menuPosition.top, left: menuPosition.left }
                    : { position: 'fixed', top: -9999, left: -9999, visibility: 'hidden' as const }
            }
            className="z-50 min-w-56 overflow-hidden rounded-md border border-slate-200 bg-white py-1 shadow-lg"
        >
            <button
                type="button"
                onClick={() => openModal('spider')}
                className="flex w-full gap-2 px-3 py-2 text-left text-slate-700 hover:bg-slate-50"
            >
                <Network className="mt-0.5 size-4 shrink-0 text-slate-400" />
                <span className="min-w-0">
                    <span className="block text-sm font-medium">Auto-expand</span>
                    <span className="block text-xs text-slate-500">Spider BFS with Settings caps</span>
                </span>
            </button>
            <button
                type="button"
                onClick={() => openModal('full_pass')}
                className="flex w-full gap-2 px-3 py-2 text-left text-slate-700 hover:bg-slate-50"
            >
                <Users className="mt-0.5 size-4 shrink-0 text-slate-400" />
                <span className="min-w-0">
                    <span className="block text-sm font-medium">Full contact pass</span>
                    <span className="block text-xs text-slate-500">Discover + full catalog, then stop</span>
                </span>
            </button>
        </div>
    ) : null;

    return (
        <>
            <div ref={containerRef} className="relative inline-block">
                <button
                    ref={buttonRef}
                    type="button"
                    onClick={() => setOpen((value) => !value)}
                    className={buttonClass}
                >
                    Expand
                    <ChevronDown className={size === 'md' ? 'size-3.5' : 'size-3'} />
                </button>
                {menu && createPortal(menu, document.body)}
            </div>

            {modalMode ? (
                <ExpandConfirmModal
                    mode={modalMode}
                    preview={preview}
                    loading={loadingPreview}
                    submitting={submitting}
                    onClose={closeModal}
                    onConfirm={confirmStart}
                />
            ) : null}
        </>
    );
}
