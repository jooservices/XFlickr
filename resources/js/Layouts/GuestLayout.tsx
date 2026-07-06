import { Link } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import type { PropsWithChildren } from 'react';

export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 py-12 text-slate-900">
            <Link href="/" className="mb-8 flex items-center gap-2">
                <Camera className="h-6 w-6 text-cyan-700" />
                <span className="text-lg font-semibold">XFlickr</span>
            </Link>

            <div className="w-full max-w-md">{children}</div>
        </div>
    );
}
