import { X } from 'lucide-react';
import { createContext, useContext, type ReactNode } from 'react';
import { createPortal } from 'react-dom';

import Button from '@/Components/ui/Button';
import { cn } from '@/lib/cn';

type ModalSize = 'sm' | 'md' | 'lg' | 'xl';

type ModalContextValue = {
    onClose?: () => void;
    closeDisabled?: boolean;
    titleId?: string;
};

const ModalContext = createContext<ModalContextValue | null>(null);

function useModalContext(): ModalContextValue {
    const context = useContext(ModalContext);
    if (!context) {
        throw new Error('Modal subcomponents must be used within <Modal>');
    }

    return context;
}

const sizeClasses: Record<ModalSize, string> = {
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
};

type ModalProps = {
    open: boolean;
    children: ReactNode;
    onClose?: () => void;
    closeDisabled?: boolean;
    size?: ModalSize;
    className?: string;
    panelClassName?: string;
    titleId?: string;
    zIndexClassName?: string;
};

function ModalRoot({
    open,
    children,
    onClose,
    closeDisabled = false,
    size = 'md',
    className,
    panelClassName,
    titleId,
    zIndexClassName = 'z-[100]',
}: ModalProps) {
    if (!open || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <ModalContext.Provider value={{ onClose, closeDisabled, titleId }}>
            <div
                className={cn(
                    'fixed inset-0 flex items-center justify-center bg-slate-900/40 p-4',
                    zIndexClassName,
                    className,
                )}
            >
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby={titleId}
                    className={cn(
                        'flex max-h-[min(90vh,40rem)] w-full flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl',
                        size === 'xl' && 'max-h-[min(92vh,52rem)]',
                        sizeClasses[size],
                        panelClassName,
                    )}
                >
                    {children}
                </div>
            </div>
        </ModalContext.Provider>,
        document.body,
    );
}

type ModalHeaderProps = {
    title?: ReactNode;
    center?: ReactNode;
    actions?: ReactNode;
    children?: ReactNode;
    className?: string;
    showClose?: boolean;
};

function ModalHeader({
    title,
    center,
    actions,
    children,
    className,
    showClose = true,
}: ModalHeaderProps) {
    const { onClose, closeDisabled, titleId } = useModalContext();

    return (
        <div
            className={cn(
                'flex shrink-0 items-center gap-3 border-b border-slate-200 px-4 py-3',
                className,
            )}
        >
            <div className="min-w-0 flex-1">
                {title !== undefined ? (
                    <h3 id={titleId} className="truncate text-lg font-semibold text-slate-900">
                        {title}
                    </h3>
                ) : (
                    children
                )}
            </div>

            <div className="flex shrink-0 items-center justify-center">{center}</div>

            <div className="flex shrink-0 items-center gap-1">
                {actions}
                {showClose && onClose ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onClose}
                        aria-label="Close"
                        disabled={closeDisabled}
                    >
                        <X className="h-5 w-5" />
                    </Button>
                ) : null}
            </div>
        </div>
    );
}

type ModalBodyProps = {
    children: ReactNode;
    className?: string;
};

function ModalBody({ children, className }: ModalBodyProps) {
    return (
        <div className={cn('min-h-0 flex-1 overflow-y-auto px-4 py-4', className)}>{children}</div>
    );
}

type ModalFooterProps = {
    children: ReactNode;
    className?: string;
};

function ModalFooter({ children, className }: ModalFooterProps) {
    return (
        <div
            className={cn(
                'flex shrink-0 items-center justify-end gap-2 border-t border-slate-200 px-4 py-3',
                className,
            )}
        >
            {children}
        </div>
    );
}

type ModalCompound = typeof ModalRoot & {
    Header: typeof ModalHeader;
    Body: typeof ModalBody;
    Footer: typeof ModalFooter;
};

const Modal = Object.assign(ModalRoot, {
    Header: ModalHeader,
    Body: ModalBody,
    Footer: ModalFooter,
}) as ModalCompound;

export default Modal;
export type { ModalProps, ModalSize };
