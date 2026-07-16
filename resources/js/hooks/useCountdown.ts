import { useCallback, useEffect, useState } from 'react';

export function useCountdown(targetIso: string | null, initialSeconds = 0): number {
    const computeSeconds = useCallback((): number => {
        if (targetIso) {
            const diff = Math.ceil((new Date(targetIso).getTime() - Date.now()) / 1000);

            return Math.max(0, diff);
        }

        return Math.max(0, initialSeconds);
    }, [initialSeconds, targetIso]);

    const [seconds, setSeconds] = useState(computeSeconds);

    useEffect(() => {
        setSeconds(computeSeconds());

        const interval = setInterval(() => {
            setSeconds(computeSeconds());
        }, 1000);

        return () => clearInterval(interval);
    }, [computeSeconds]);

    return seconds;
}

export function formatCountdown(totalSeconds: number): string {
    if (totalSeconds <= 0) {
        return 'now';
    }

    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }

    if (minutes > 0) {
        return `${minutes}m ${seconds}s`;
    }

    return `${seconds}s`;
}
