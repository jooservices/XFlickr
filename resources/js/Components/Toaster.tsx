import { X } from 'lucide-react';
import { useEffect, useState } from 'react';

import { cn } from '@/lib/cn';
import { dismissToast, subscribeToasts, type ToastItem, type ToastVariant } from '@/lib/toast';

const variantStyles: Record<ToastVariant, string> = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-950 shadow-emerald-100/80',
    error: 'border-red-200 bg-red-50 text-red-950 shadow-red-100/80',
    info: 'border-slate-200 bg-white text-slate-950 shadow-slate-200/80',
};

function ToastCard({ item }: { item: ToastItem }) {
    return (
        <div
            role="status"
            className={cn(
                'pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border px-4 py-3 shadow-lg',
                variantStyles[item.variant],
            )}
        >
            <p className="flex-1 text-sm font-medium leading-5">{item.message}</p>
            <button
                type="button"
                className="rounded p-0.5 opacity-70 transition hover:opacity-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                aria-label="Dismiss notification"
                onClick={() => dismissToast(item.id)}
            >
                <X className="h-4 w-4" aria-hidden />
            </button>
        </div>
    );
}

export default function Toaster() {
    const [items, setItems] = useState<ToastItem[]>([]);

    useEffect(() => subscribeToasts(setItems), []);

    if (items.length === 0) {
        return null;
    }

    return (
        <div
            aria-live="polite"
            aria-relevant="additions"
            className="pointer-events-none fixed top-4 right-4 z-[100] flex w-[min(100vw-2rem,24rem)] flex-col gap-2"
        >
            {items.map((item) => (
                <ToastCard key={item.id} item={item} />
            ))}
        </div>
    );
}
