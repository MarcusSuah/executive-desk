import { router, useForm } from '@inertiajs/react';
import { Download, FileText, Paperclip, Trash2, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import { useRef } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useAuth } from '@/hooks/use-auth';
import { formatDateTime } from '@/lib/datetime';
import { formatBytes } from '@/lib/labels';
import { destroy } from '@/routes/attachments';
import type { Attachment } from '@/types';

const ACCEPT =
    '.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.odt,.odp,.ods';

/**
 * Lists private files and uploads new ones. Files are downloaded through
 * authorized routes; uploading a file with the same name creates a new version.
 */
export default function AttachmentsPanel({
    title = 'Documents',
    attachments,
    uploadUrl,
    canUpload,
}: {
    title?: string;
    attachments: Attachment[];
    uploadUrl: string;
    canUpload: boolean;
}) {
    const { timezone } = useAuth();
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm<{ file: File | null; label: string }>({
        file: null,
        label: '',
    });

    const upload = (event: FormEvent) => {
        event.preventDefault();
        form.post(uploadUrl, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset();

                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Paperclip className="size-4" aria-hidden />
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {attachments.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No files attached.
                    </p>
                ) : (
                    <ul className="divide-y rounded-md border">
                        {attachments.map((attachment) => (
                            <li
                                key={attachment.id}
                                className="flex items-center gap-3 px-3 py-2"
                            >
                                <FileText
                                    className="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {attachment.original_name}
                                        <span className="ml-2 text-xs font-normal text-muted-foreground">
                                            v{attachment.version}
                                        </span>
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {[
                                            attachment.label,
                                            formatBytes(attachment.size),
                                            attachment.uploaded_by,
                                            formatDateTime(
                                                attachment.created_at,
                                                timezone,
                                            ),
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    asChild
                                    aria-label={`Download ${attachment.original_name}`}
                                >
                                    <a href={attachment.download_url}>
                                        <Download className="size-4" />
                                    </a>
                                </Button>
                                {attachment.can_delete && (
                                    <ConfirmDialog
                                        trigger={
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Remove ${attachment.original_name}`}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        }
                                        title="Remove this file?"
                                        description={`${attachment.original_name} (version ${attachment.version}) will be permanently removed.`}
                                        confirmLabel="Remove file"
                                        destructive
                                        onConfirm={(close, setBusy) => {
                                            setBusy(true);
                                            router.delete(
                                                destroy.url(attachment.id),
                                                {
                                                    preserveScroll: true,
                                                    onFinish: () => {
                                                        setBusy(false);
                                                        close();
                                                    },
                                                },
                                            );
                                        }}
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                {canUpload && (
                    <form onSubmit={upload} className="space-y-2">
                        <div className="grid gap-2 sm:grid-cols-[1fr_12rem_auto]">
                            <Input
                                ref={fileInput}
                                type="file"
                                accept={ACCEPT}
                                aria-label="File to upload"
                                onChange={(event) =>
                                    form.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <Input
                                value={form.data.label}
                                onChange={(event) =>
                                    form.setData('label', event.target.value)
                                }
                                placeholder="Label (optional)"
                                aria-label="File label"
                                maxLength={255}
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={form.processing || !form.data.file}
                            >
                                {form.processing ? <Spinner /> : <Upload />}
                                Upload
                            </Button>
                        </div>
                        {form.progress && (
                            <progress
                                value={form.progress.percentage}
                                max={100}
                                className="h-1 w-full"
                            />
                        )}
                        <InputError message={form.errors.file} />
                        <p className="text-xs text-muted-foreground">
                            PDF, Office, OpenDocument, CSV, text, or image files
                            up to 20 MB.
                        </p>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}
