import { router, useForm } from '@inertiajs/react';
import { ListChecks, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { destroy, update } from '@/routes/checklist-items';
import type { ChecklistItem } from '@/types';

/**
 * A checklist with add, tick, and remove actions. The store URL belongs to the parent record.
 */
export default function ChecklistPanel({
    title = 'Checklist',
    items,
    storeUrl,
    canEdit,
}: {
    title?: string;
    items: ChecklistItem[];
    storeUrl: string;
    canEdit: boolean;
}) {
    const form = useForm({ title: '' });
    const completed = items.filter((item) => item.is_completed).length;

    const add = (event: FormEvent) => {
        event.preventDefault();
        form.post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const toggle = (item: ChecklistItem, checked: boolean) =>
        router.patch(
            update.url(item.id),
            { is_completed: checked },
            { preserveScroll: true },
        );

    const remove = (item: ChecklistItem) =>
        router.delete(destroy.url(item.id), { preserveScroll: true });

    return (
        <Card className="gap-4">
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2 text-base">
                    <ListChecks className="size-4" aria-hidden />
                    {title}
                </CardTitle>
                {items.length > 0 && (
                    <span className="text-sm text-muted-foreground tabular-nums">
                        {completed}/{items.length} done
                    </span>
                )}
            </CardHeader>
            <CardContent className="space-y-3">
                {items.length > 0 && (
                    <div
                        className="h-1.5 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-valuemin={0}
                        aria-valuemax={items.length}
                        aria-valuenow={completed}
                        aria-label="Checklist progress"
                    >
                        <div
                            className="h-full bg-emerald-500 transition-all"
                            style={{
                                width: `${(completed / items.length) * 100}%`,
                            }}
                        />
                    </div>
                )}

                {items.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No checklist items yet.
                    </p>
                )}

                <ul className="space-y-1">
                    {items.map((item) => (
                        <li
                            key={item.id}
                            className="group flex items-center gap-3 rounded-md px-1 py-1.5 hover:bg-muted/50"
                        >
                            <Checkbox
                                id={`checklist-${item.id}`}
                                checked={item.is_completed}
                                disabled={!canEdit}
                                onCheckedChange={(checked) =>
                                    toggle(item, checked === true)
                                }
                            />
                            <label
                                htmlFor={`checklist-${item.id}`}
                                className={cn('flex-1 text-sm', {
                                    'text-muted-foreground line-through':
                                        item.is_completed,
                                })}
                            >
                                {item.title}
                            </label>
                            {canEdit && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 opacity-0 group-hover:opacity-100 focus:opacity-100"
                                    onClick={() => remove(item)}
                                    aria-label={`Remove ${item.title}`}
                                >
                                    <Trash2 className="size-3.5" />
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>

                {canEdit && (
                    <form onSubmit={add} className="space-y-1">
                        <div className="flex gap-2">
                            <Input
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                placeholder="Add an item"
                                aria-label="New checklist item"
                                maxLength={500}
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={
                                    form.processing ||
                                    form.data.title.trim() === ''
                                }
                            >
                                <Plus />
                                Add
                            </Button>
                        </div>
                        <InputError message={form.errors.title} />
                    </form>
                )}
            </CardContent>
        </Card>
    );
}
