<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stores uploaded files on the private disk and serves them through authorized downloads.
 *
 * Files are stored under random names; the original name is kept only as metadata. Uploading
 * a file with the same name as an existing one on the same record creates a new version.
 */
class AttachmentStore
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Store an uploaded file against a record.
     */
    public function store(Model $attachable, UploadedFile $file, User $uploader, ?string $label = null): Attachment
    {
        $disk = config('executive-desk.uploads.disk');
        $originalName = Str::limit(basename($file->getClientOriginalName()), 250, '');
        $directory = 'attachments/'.$attachable->getMorphClass().'/'.$attachable->getKey();
        $checksum = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs($directory, Str::uuid()->toString().'.'.$file->extension(), $disk);

        if ($checksum === false || $path === false) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        return DB::transaction(function () use ($attachable, $file, $uploader, $label, $disk, $originalName, $path, $checksum): Attachment {
            $previousVersion = Attachment::query()
                ->whereMorphedTo('attachable', $attachable)
                ->where('original_name', $originalName)
                ->lockForUpdate()
                ->max('version');

            $attachment = new Attachment;
            $attachment->attachable()->associate($attachable);
            $attachment->uploaded_by = $uploader->id;
            $attachment->disk = $disk;
            $attachment->path = $path;
            $attachment->original_name = $originalName;
            $attachment->mime_type = $file->getMimeType() ?? 'application/octet-stream';
            $attachment->size = $file->getSize();
            $attachment->checksum = $checksum;
            $attachment->version = ((int) $previousVersion) + 1;
            $attachment->label = $label;
            $attachment->save();

            $this->audit->log('attachment.uploaded', $attachable, [], [
                'attachment_id' => $attachment->id,
                'file' => $originalName,
                'version' => $attachment->version,
            ], $uploader);

            return $attachment;
        });
    }

    /**
     * Stream the file as a download with headers that prevent inline rendering.
     */
    public function download(Attachment $attachment, User $user): StreamedResponse
    {
        $this->audit->log('attachment.downloaded', $attachment->attachable, [], [
            'attachment_id' => $attachment->id,
            'file' => $attachment->original_name,
        ], $user);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Soft delete the attachment record and remove the stored file.
     */
    public function delete(Attachment $attachment, User $user): void
    {
        DB::transaction(function () use ($attachment, $user): void {
            $attachment->delete();

            $this->audit->log('attachment.deleted', $attachment->attachable, [], [
                'attachment_id' => $attachment->id,
                'file' => $attachment->original_name,
            ], $user);
        });

        Storage::disk($attachment->disk)->delete($attachment->path);
    }
}
