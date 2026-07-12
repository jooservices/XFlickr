import { useEffect, useState } from 'react';

export function useElementSize<T extends HTMLElement>(): {
    ref: (node: T | null) => void;
    width: number;
    height: number;
} {
    const [node, setNode] = useState<T | null>(null);
    const [size, setSize] = useState({ width: 1280, height: 640 });

    useEffect(() => {
        if (!node) {
            return;
        }

        const observer = new ResizeObserver(([entry]) => {
            if (!entry) {
                return;
            }

            setSize({
                width: Math.max(entry.contentRect.width, 320),
                height: Math.max(entry.contentRect.height, 320),
            });
        });

        observer.observe(node);

        return () => observer.disconnect();
    }, [node]);

    return { ref: setNode, ...size };
}
