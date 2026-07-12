import { useEffect, useRef } from 'react';

import { toast } from '@/lib/toast';

type FlashMessages = {
    success: string | null;
    error: string | null;
};

export function useFlashToast(flash: FlashMessages): void {
    const shownRef = useRef<{ success: string | null; error: string | null }>({
        success: null,
        error: null,
    });

    useEffect(() => {
        if (flash.success && flash.success !== shownRef.current.success) {
            toast.success(flash.success);
            shownRef.current.success = flash.success;
        }

        if (flash.error && flash.error !== shownRef.current.error) {
            toast.error(flash.error);
            shownRef.current.error = flash.error;
        }
    }, [flash.success, flash.error]);
}
