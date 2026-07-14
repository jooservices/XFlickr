import type { ReactNode } from 'react';

export default function ContactGraphLegend(): ReactNode {
    return (
        <p className="mt-0.5 text-[11px] text-slate-400">
            Dot size &amp; darkness = photos indexed · ★ = starred
        </p>
    );
}
