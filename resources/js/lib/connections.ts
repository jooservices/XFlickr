export type ConnectionsProvider = 'flickr' | 'storage';

export function connectionsPath(options?: { provider?: ConnectionsProvider }): string {
    const provider = options?.provider ?? 'flickr';

    if (provider === 'flickr') {
        return '/connections';
    }

    return '/connections?provider=storage';
}
