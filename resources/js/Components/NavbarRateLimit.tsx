import RateLimitMeter from '@/Components/RateLimitMeter';
import { flickrAccountLabel, useFlickrRateLimit } from '@/hooks/useFlickrRateLimit';
import { cn } from '@/lib/cn';

export default function NavbarRateLimit() {
    const { snapshot, selectedNsid, setSelectedNsid, selectedRateLimit, loading } = useFlickrRateLimit();

    if (!snapshot || snapshot.accounts.length === 0) {
        return null;
    }

    const showAccountPicker = snapshot.accounts.length > 1;

    return (
        <div
            className={cn(
                'flex min-w-0 shrink-0 items-start gap-2',
                loading && 'opacity-80',
            )}
            aria-live="polite"
            aria-busy={loading}
        >
            {showAccountPicker ? (
                <select
                    value={selectedNsid ?? ''}
                    onChange={(event) => setSelectedNsid(event.target.value)}
                    className="max-w-[8rem] truncate rounded-md border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700"
                    aria-label="Flickr account for API quota"
                >
                    {snapshot.accounts.map((row) => (
                        <option key={row.account.nsid} value={row.account.nsid}>
                            {flickrAccountLabel(row.account)}
                        </option>
                    ))}
                </select>
            ) : null}

            {selectedRateLimit ? (
                <RateLimitMeter
                    rateLimit={selectedRateLimit}
                    label="Flickr API quota"
                    variant="navbar"
                />
            ) : null}
        </div>
    );
}
