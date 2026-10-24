import { router } from '@inertiajs/react';
import { CloudOff, RefreshCw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { useAuth } from '@/hooks/use-auth';
import { useOnlineStatus } from '@/hooks/use-online-status';
import {
    pendingMutations,
    removeMutation,
    saveSnapshot,
    xsrfToken,
} from '@/lib/offline-store';
import type { OfflineSnapshot } from '@/lib/offline-store';
import { snapshot as snapshotRoute, sync as syncRoute } from '@/routes/offline';

const SNAPSHOT_INTERVAL_MS = 10 * 60 * 1000;

type SyncResult = {
    client_mutation_id: string;
    result: 'applied' | 'duplicate' | 'conflict' | 'rejected';
    message: string;
};

/**
 * Shows an offline indicator, keeps a small offline snapshot fresh, and uploads
 * changes queued while offline once the connection returns.
 */
export function OfflineSync() {
    const { user } = useAuth();
    const online = useOnlineStatus();
    const [pending, setPending] = useState(0);
    const syncing = useRef(false);

    useEffect(() => {
        if (!online || !user) {
            return;
        }

        const refreshSnapshot = () =>
            fetch(snapshotRoute.url(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((response) =>
                    response.ok
                        ? (response.json() as Promise<OfflineSnapshot>)
                        : null,
                )
                .then((snapshot) => snapshot && saveSnapshot(snapshot))
                .catch(() => undefined);

        const uploadQueued = async () => {
            if (syncing.current) {
                return;
            }

            syncing.current = true;

            try {
                const mutations = await pendingMutations(user.id);
                setPending(mutations.length);

                if (mutations.length === 0) {
                    return;
                }

                const response = await fetch(syncRoute.url(), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify({ mutations }),
                });

                if (!response.ok) {
                    toast.error(
                        'Offline changes could not be synchronized yet. They will be retried.',
                    );

                    return;
                }

                const { results } = (await response.json()) as {
                    results: SyncResult[];
                };

                for (const result of results) {
                    await removeMutation(result.client_mutation_id);

                    if (result.result === 'applied') {
                        toast.success(result.message);
                    } else if (result.result !== 'duplicate') {
                        toast.error(result.message);
                    }
                }

                setPending(0);
                router.reload();
            } catch {
                // Leave the queue in place; it is retried on the next reconnection.
            } finally {
                syncing.current = false;
            }
        };

        void uploadQueued();
        void refreshSnapshot();
        const timer = window.setInterval(refreshSnapshot, SNAPSHOT_INTERVAL_MS);

        return () => window.clearInterval(timer);
    }, [online, user]);

    useEffect(() => {
        if (!online && user) {
            pendingMutations(user.id)
                .then((mutations) => setPending(mutations.length))
                .catch(() => undefined);
        }
    }, [online, user]);

    if (online && pending === 0) {
        return null;
    }

    return (
        <div
            role="status"
            className="flex items-center gap-2 rounded-md bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-100"
        >
            {online ? (
                <>
                    <RefreshCw className="size-3.5 animate-spin" aria-hidden />
                    Synchronizing {pending} change(s)…
                </>
            ) : (
                <>
                    <CloudOff className="size-3.5" aria-hidden />
                    Offline{pending > 0 && ` · ${pending} change(s) waiting`}
                </>
            )}
        </div>
    );
}
