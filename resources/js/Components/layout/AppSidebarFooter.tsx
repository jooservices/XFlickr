import { APP_BOTTOM_RAIL_FOOTER_CLASS } from '@/Components/Layout/appBottomRail';

/** Empty matching strip for the sidebar bottom rail (content lives in the main status footer). */
export default function AppSidebarFooter() {
    return (
        <div
            className={APP_BOTTOM_RAIL_FOOTER_CLASS}
            aria-hidden="true"
            data-testid="app-sidebar-footer"
        />
    );
}
