import type { Auth } from '@/types/auth';

declare global {
    interface Window {
        gtag?: (
            command: string,
            eventName: string,
            parameters?: Record<string, unknown>,
        ) => void;
        fbq?: (command: string, eventName: string) => void;
    }
}

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            companyBrand: {
                name: string;
                unit: string;
                logoUrl: string | null;
            } | null;
            seo: Record<string, string>;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
