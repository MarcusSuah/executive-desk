import { toast } from 'sonner';
import { useAuth } from '@/hooks/use-auth';
import { queueMutation } from '@/lib/offline-store';
import type { Task } from '@/types';

/**
 * Applies eligible changes immediately when online, or queues them locally while offline.
 */
export function useOfflineQueue() {
    const { user } = useAuth();

    const changeTaskStatus = (
        task: Task,
        status: Task['status'],
        applyOnline: () => void,
    ): void => {
        if (navigator.onLine) {
            applyOnline();

            return;
        }

        queueMutation({
            client_mutation_id: crypto.randomUUID(),
            user_id: user.id,
            type: 'task.status',
            task_id: task.id,
            task_title: task.title,
            status,
            expected_updated_at: task.updated_at,
            queued_at: new Date().toISOString(),
        })
            .then(() =>
                toast.info(
                    `You are offline. “${task.title}” will be updated when you reconnect.`,
                ),
            )
            .catch(() =>
                toast.error(
                    'This change could not be saved offline. Try again when you are connected.',
                ),
            );
    };

    return { changeTaskStatus };
}
