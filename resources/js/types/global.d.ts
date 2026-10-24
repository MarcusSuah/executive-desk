import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            inbox: {
                unread: number;
                items: {
                    id: string;
                    kind: string;
                    title: string;
                    body: string;
                    read: boolean;
                    created_at: string | null;
                }[];
            } | null;
            pwa: {
                vapidPublicKey: string | null;
            };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
