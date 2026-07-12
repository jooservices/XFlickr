import { useEffect, useRef } from 'react';

interface UseInfiniteScrollOptions {
    hasMore: boolean;
    loading: boolean;
    onLoadMore: () => void;
    rootMargin?: string;
}

export function useInfiniteScroll({
    hasMore,
    loading,
    onLoadMore,
    rootMargin = '240px',
}: UseInfiniteScrollOptions) {
    const sentinelRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const element = sentinelRef.current;
        if (!element || !hasMore) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0]?.isIntersecting && !loading) {
                    onLoadMore();
                }
            },
            { rootMargin },
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [hasMore, loading, onLoadMore, rootMargin]);

    return sentinelRef;
}
