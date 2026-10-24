/**
 * Per-user IndexedDB storage for offline support.
 *
 * Only a limited, non-sensitive snapshot (upcoming appointment times/titles and open
 * task titles) and queued task status changes are stored. Nothing here is treated as
 * authoritative: the server validates every queued change on reconnection. Data is
 * cleared when the user signs out.
 */

const DB_NAME = 'executive-desk';
const DB_VERSION = 1;
const SNAPSHOTS = 'snapshots';
const MUTATIONS = 'mutations';

export type OfflineSnapshot = {
    user_id: number;
    user_name: string;
    timezone: string;
    synced_at: string;
    events: {
        id: number;
        title: string;
        starts_at: string;
        ends_at: string;
        all_day: boolean;
        location: string | null;
        status: string;
    }[];
    tasks: {
        id: number;
        title: string;
        due_at: string | null;
        priority: string;
        status: string;
        updated_at: string | null;
    }[];
};

export type QueuedMutation = {
    client_mutation_id: string;
    user_id: number;
    type: 'task.status';
    task_id: number;
    task_title: string;
    status: string;
    expected_updated_at: string | null;
    queued_at: string;
};

function openDatabase(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        if (typeof indexedDB === 'undefined') {
            reject(new Error('IndexedDB is not available.'));

            return;
        }

        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;

            if (!db.objectStoreNames.contains(SNAPSHOTS)) {
                db.createObjectStore(SNAPSHOTS, { keyPath: 'user_id' });
            }

            if (!db.objectStoreNames.contains(MUTATIONS)) {
                db.createObjectStore(MUTATIONS, {
                    keyPath: 'client_mutation_id',
                }).createIndex('user_id', 'user_id');
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function withStore<T>(
    storeName: string,
    mode: IDBTransactionMode,
    action: (store: IDBObjectStore) => IDBRequest<T> | void,
): Promise<T | undefined> {
    const db = await openDatabase();

    return new Promise((resolve, reject) => {
        const transaction = db.transaction(storeName, mode);
        const request = action(transaction.objectStore(storeName));
        let result: T | undefined;

        if (request) {
            request.onsuccess = () => {
                result = request.result;
            };
        }

        transaction.oncomplete = () => {
            db.close();
            resolve(result);
        };
        transaction.onerror = () => {
            db.close();
            reject(transaction.error);
        };
    });
}

export function saveSnapshot(snapshot: OfflineSnapshot): Promise<unknown> {
    return withStore(SNAPSHOTS, 'readwrite', (store) => store.put(snapshot));
}

export function getSnapshot(
    userId: number,
): Promise<OfflineSnapshot | undefined> {
    return withStore<OfflineSnapshot>(SNAPSHOTS, 'readonly', (store) =>
        store.get(userId),
    );
}

export function queueMutation(mutation: QueuedMutation): Promise<unknown> {
    return withStore(MUTATIONS, 'readwrite', (store) => store.put(mutation));
}

export async function pendingMutations(
    userId: number,
): Promise<QueuedMutation[]> {
    const all = await withStore<QueuedMutation[]>(
        MUTATIONS,
        'readonly',
        (store) => store.index('user_id').getAll(userId),
    );

    return (all ?? []).sort((a, b) => a.queued_at.localeCompare(b.queued_at));
}

export function removeMutation(clientMutationId: string): Promise<unknown> {
    return withStore(MUTATIONS, 'readwrite', (store) =>
        store.delete(clientMutationId),
    );
}

/**
 * Remove every offline record (called on sign-out).
 */
export async function clearOfflineData(): Promise<void> {
    await Promise.all([
        withStore(SNAPSHOTS, 'readwrite', (store) => store.clear()),
        withStore(MUTATIONS, 'readwrite', (store) => store.clear()),
    ]).catch(() => undefined);
}

/**
 * Read the XSRF token cookie Laravel sets, for JSON requests made with fetch.
 */
export function xsrfToken(): string {
    const match = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
}
