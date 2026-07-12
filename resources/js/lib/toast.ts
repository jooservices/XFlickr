export type ToastVariant = 'success' | 'error' | 'info';

export type ToastItem = {
    id: string;
    message: string;
    variant: ToastVariant;
    durationMs: number;
};

type ToastListener = (toasts: ToastItem[]) => void;

const DEFAULT_DURATION_MS = 4_500;

let nextId = 0;
let toasts: ToastItem[] = [];
const listeners = new Set<ToastListener>();
const dismissTimers = new Map<string, ReturnType<typeof setTimeout>>();

function emit(): void {
    for (const listener of listeners) {
        listener([...toasts]);
    }
}

function scheduleDismiss(id: string, durationMs: number): void {
    const existing = dismissTimers.get(id);

    if (existing) {
        clearTimeout(existing);
    }

    const timer = setTimeout(() => {
        dismissTimers.delete(id);
        dismissToast(id);
    }, durationMs);

    dismissTimers.set(id, timer);
}

function pushToast(message: string, variant: ToastVariant, durationMs = DEFAULT_DURATION_MS): string {
    const id = `toast-${nextId++}`;
    const item: ToastItem = { id, message, variant, durationMs };
    toasts = [item, ...toasts].slice(0, 5);
    emit();
    scheduleDismiss(id, durationMs);

    return id;
}

export function dismissToast(id: string): void {
    const timer = dismissTimers.get(id);

    if (timer) {
        clearTimeout(timer);
        dismissTimers.delete(id);
    }

    const next = toasts.filter((toast) => toast.id !== id);

    if (next.length === toasts.length) {
        return;
    }

    toasts = next;
    emit();
}

export function subscribeToasts(listener: ToastListener): () => void {
    listeners.add(listener);
    listener([...toasts]);

    return () => {
        listeners.delete(listener);
    };
}

export const toast = {
    success(message: string, durationMs?: number): string {
        return pushToast(message, 'success', durationMs);
    },
    error(message: string, durationMs?: number): string {
        return pushToast(message, 'error', durationMs ?? 6_000);
    },
    info(message: string, durationMs?: number): string {
        return pushToast(message, 'info', durationMs);
    },
    dismiss: dismissToast,
};

/** Clears active toasts — for unit tests only. */
export function resetToastsForTests(): void {
    for (const timer of dismissTimers.values()) {
        clearTimeout(timer);
    }

    dismissTimers.clear();
    toasts = [];
    emit();
}
