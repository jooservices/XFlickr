import type { PropsWithChildren, WheelEvent } from 'react';

interface ContactGraphDetailShellProps extends PropsWithChildren {
    onClose: () => void;
}

function stopWheelPropagation(event: WheelEvent) {
    event.stopPropagation();
}

export default function ContactGraphDetailShell({ children, onClose }: ContactGraphDetailShellProps) {
    return (
        <>
            {/* Desktop: right sidebar */}
            <aside
                className="hidden min-h-0 w-72 shrink-0 flex-col border-l border-slate-200 bg-white lg:flex"
                onWheel={stopWheelPropagation}
            >
                <div className="flex min-h-0 flex-1 flex-col">{children}</div>
            </aside>

            {/* Mobile: bottom card */}
            <div className="pointer-events-none fixed inset-x-0 bottom-0 z-[110] p-4 lg:hidden">
                <aside
                    className="pointer-events-auto mx-auto flex max-h-[70vh] w-full max-w-lg flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl"
                    onWheel={stopWheelPropagation}
                >
                    <div className="flex min-h-0 flex-1 flex-col">{children}</div>
                </aside>
            </div>

            {/* Mobile: tap backdrop to close */}
            <button
                type="button"
                className="fixed inset-0 z-[105] bg-slate-900/20 lg:hidden"
                aria-label="Close contact panel"
                onClick={onClose}
            />
        </>
    );
}
