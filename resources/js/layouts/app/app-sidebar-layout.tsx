import { RouteAnnouncer } from '@/components/a11y/route-announcer';
import { SessionTimeoutWarning } from '@/components/a11y/session-timeout-warning';
import { SkipLink } from '@/components/a11y/skip-link';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <SkipLink />
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div
                    id="main-content"
                    tabIndex={-1}
                    className="flex flex-1 flex-col"
                >
                    {children}
                </div>
            </AppContent>
            <RouteAnnouncer />
            <SessionTimeoutWarning />
        </AppShell>
    );
}
