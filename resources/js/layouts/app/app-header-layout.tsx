import { useState } from 'react';
import { AppContent } from '@/components/app-content';
import { AppHeader } from '@/components/app-header';
import { AppSidebar } from '@/components/app-sidebar';
import type { AppLayoutProps } from '@/types';

export default function AppHeaderLayout({
    children,
    breadcrumbs,
}: AppLayoutProps) {
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

    return (
        <div className="drawer min-h-svh bg-base-200 lg:drawer-open">
            <input id="app-drawer" type="checkbox" className="drawer-toggle" />
            <div className="drawer-content flex min-w-0 flex-col">
                <AppHeader
                    breadcrumbs={breadcrumbs}
                    sidebarCollapsed={sidebarCollapsed}
                    onSidebarToggle={() =>
                        setSidebarCollapsed((value) => !value)
                    }
                />
                <AppContent variant="header" className="min-w-0">
                    {children}
                </AppContent>
            </div>
            <div className="drawer-side z-50 lg:z-30">
                <label
                    htmlFor="app-drawer"
                    aria-label="Fechar menu lateral"
                    className="drawer-overlay"
                />
                <AppSidebar collapsed={sidebarCollapsed} />
            </div>
        </div>
    );
}
