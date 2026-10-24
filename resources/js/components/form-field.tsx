import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export default function FormField({
    id,
    label,
    error,
    hint,
    required = false,
    className,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: ReactNode;
    required?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={id}>
                {label}
                {required && (
                    <span className="text-red-600" aria-hidden>
                        *
                    </span>
                )}
            </Label>
            {children}
            {hint && !error && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}
