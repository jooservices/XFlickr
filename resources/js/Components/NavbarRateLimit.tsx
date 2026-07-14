import RateLimitMeter from '@/Components/RateLimitMeter';
import { flickrAccountLabel } from '@/hooks/useFlickrRateLimit';
import { cn } from '@/lib/cn';
import type { FlickrRateLimitSnapshot, RateLimitState } from '@/types';

interface NavbarRateLimitProps {
    snapshot: FlickrRateLimitSnapshot | null;
    selectedNsid: string | null;
    setSelectedNsid: (nsid: string) => void;
    selectedRateLimit: RateLimitState | null;
    loading: boolean;
}

export default function NavbarRateLimit({
    snapshot,
    selectedNsid,
    setSelectedNsid,
    selectedRateLimit,
    loading,
}: NavbarRateLimitProps) {
    if (!snapshot || snapshot.accounts.length === 0) {
        return null;
    }

    const showAccountPicker = snapshot.accounts.length > 1;

    return (
        <div
            className={cn(
                'flex min-w-0 shrink-0 items-center gap-2',
                loading && 'opacity-80',
            )}
            aria-live="polite"
            aria-busy={loading}
        >
            {showAccountPicker ? (
                <select
                    value={selectedNsid ?? ''}
                    onChange={(event) => setSelectedNsid(event.target.value)}
                    className="max-w-[8rem] truncate rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-700"
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
                    label="Flickr API"
                    variant="footer"
                />
            ) : null}
        </div>
    );
}
