import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    store as storeSubscription,
    unsubscribe as unsubscribeRoute,
} from '@/routes/push-subscriptions';

export type PushState =
    | 'unsupported'
    | 'unconfigured'
    | 'denied'
    | 'subscribed'
    | 'available';

/**
 * Convert a base64url VAPID key to the byte array the Push API expects.
 */
function urlBase64ToUint8Array(base64: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = new Uint8Array(new ArrayBuffer(raw.length));

    for (let index = 0; index < raw.length; index++) {
        bytes[index] = raw.charCodeAt(index);
    }

    return bytes;
}

function isSupported(): boolean {
    return (
        typeof window !== 'undefined' &&
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
    );
}

/**
 * Manage this browser's Web Push subscription. Permission is only requested
 * from an explicit user action (enable()).
 */
export function usePushNotifications() {
    const { pwa } = usePage().props;
    const [state, setState] = useState<PushState>('available');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!isSupported()) {
            setState('unsupported');

            return;
        }

        if (!pwa.vapidPublicKey) {
            setState('unconfigured');

            return;
        }

        if (Notification.permission === 'denied') {
            setState('denied');

            return;
        }

        navigator.serviceWorker.ready
            .then((registration) => registration.pushManager.getSubscription())
            .then((subscription) =>
                setState(subscription ? 'subscribed' : 'available'),
            )
            .catch(() => setState('available'));
    }, [pwa.vapidPublicKey]);

    const enable = async (): Promise<void> => {
        if (!pwa.vapidPublicKey) {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                setState(permission === 'denied' ? 'denied' : 'available');

                return;
            }

            const registration =
                await navigator.serviceWorker.register('/sw.js');
            await navigator.serviceWorker.ready;

            const subscription =
                (await registration.pushManager.getSubscription()) ??
                (await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(
                        pwa.vapidPublicKey,
                    ),
                }));

            const json = subscription.toJSON();
            const encodings =
                (
                    PushManager as unknown as {
                        supportedContentEncodings?: string[];
                    }
                ).supportedContentEncodings ?? [];

            router.post(
                storeSubscription.url(),
                {
                    endpoint: json.endpoint ?? '',
                    keys: {
                        p256dh: json.keys?.p256dh ?? '',
                        auth: json.keys?.auth ?? '',
                    },
                    content_encoding: encodings.includes('aes128gcm')
                        ? 'aes128gcm'
                        : 'aesgcm',
                },
                {
                    preserveScroll: true,
                    onSuccess: () => setState('subscribed'),
                },
            );
        } catch {
            setError(
                'Push notifications could not be enabled in this browser.',
            );
        } finally {
            setBusy(false);
        }
    };

    const disable = async (): Promise<void> => {
        setBusy(true);

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription =
                await registration.pushManager.getSubscription();

            if (subscription) {
                const endpoint = subscription.endpoint;
                await subscription.unsubscribe();
                router.post(
                    unsubscribeRoute.url(),
                    { endpoint },
                    { preserveScroll: true },
                );
            }

            setState('available');
        } finally {
            setBusy(false);
        }
    };

    return { state, busy, error, enable, disable };
}
