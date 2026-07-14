import { cn } from '@/lib/cn';

/** Shared bottom-rail chrome for sidebar footer + main status footer. */
export const APP_BOTTOM_RAIL_CLASS =
    'border-t border-slate-200 bg-white/95 backdrop-blur-sm';

/** Fixed height — both footers must always match exactly. */
export const APP_BOTTOM_RAIL_HEIGHT_CLASS = 'h-12';

/** Shared strip: same chrome + same fixed height on sidebar and content footers. */
export const APP_BOTTOM_RAIL_FOOTER_CLASS = cn(
    APP_BOTTOM_RAIL_CLASS,
    APP_BOTTOM_RAIL_HEIGHT_CLASS,
    'flex shrink-0 items-center overflow-hidden',
);

/** Reset AppShell `.joo-layout-sidebar-footer` padding/border so the rail owns chrome. */
export const APP_SIDEBAR_FOOTER_RESET_CLASS =
    '[&_.joo-layout-sidebar-footer]:!border-0 [&_.joo-layout-sidebar-footer]:!p-0 [&_.joo-layout-sidebar-footer]:!m-0 [&_.joo-layout-sidebar-footer]:text-inherit';
