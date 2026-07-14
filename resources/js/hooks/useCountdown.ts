import { useEffect, useState } from 'react';

function secondsUntil(targetIso: string | null, initialSeconds: number): number {
    if (targetIso) {
        const diff = Math.ceil((new Date(targetIso).getTime() - Date.now()) / 1000);

        return Math.max(0, diff);
    }

    return Math.max(0, initialSeconds);
}

export function useCountdown(targetIso: string | null, initialSeconds = 0): number {
    const [seconds, setSeconds] = useState(() => secondsUntil(targetIso, initialSeconds));

    useEffect(() => {
        const computeSeconds = () => secondsUntil(targetIso, initialSeconds);

        setSeconds(computeSeconds());

        const interval = setInterval(() => {
            setSeconds(computeSeconds());
        }, 1000);

        return () => clearInterval(interval);
    }, [targetIso, initialSeconds]);

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
