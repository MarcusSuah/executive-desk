import type { ReactNode } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

/**
 * Asks for confirmation before running a destructive or important action.
 *
 * Pass `open` and `onOpenChange` instead of a trigger to open it from elsewhere,
 * such as a dropdown menu item.
 */
export default function ConfirmDialog({
    trigger,
    title,
    description,
    confirmLabel = 'Confirm',
    destructive = false,
    onConfirm,
    children,
    open: controlledOpen,
    onOpenChange,
}: {
    trigger?: ReactNode;
    title: string;
    description?: ReactNode;
    confirmLabel?: string;
    destructive?: boolean;
    onConfirm: (close: () => void, setBusy: (busy: boolean) => void) => void;
    children?: ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
    const open = controlledOpen ?? uncontrolledOpen;
    const setOpen = onOpenChange ?? setUncontrolledOpen;
    const [busy, setBusy] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            {trigger && <DialogTrigger asChild>{trigger}</DialogTrigger>}
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                {description && (
                    <DialogDescription>{description}</DialogDescription>
                )}
                {children}
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary" disabled={busy}>
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        variant={destructive ? 'destructive' : 'default'}
                        disabled={busy}
                        onClick={() => onConfirm(() => setOpen(false), setBusy)}
                    >
                        {busy && <Spinner />}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
