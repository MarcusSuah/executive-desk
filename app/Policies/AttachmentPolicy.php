<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\CalendarEvent;
use App\Models\Presentation;
use App\Models\Task;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * Determine whether the user can download the file. Access follows the parent record.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        $parent = $attachment->attachable;

        return $parent !== null && $user->can('view', $parent);
    }

    /**
     * Determine whether the user can delete the file.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        $parent = $attachment->attachable;

        return match (true) {
            $parent instanceof CalendarEvent => $user->can('attachFiles', $parent),
            $parent instanceof Task => $user->can('update', $parent) || $attachment->uploaded_by === $user->id,
            $parent instanceof Presentation => $user->can('update', $parent) || $attachment->uploaded_by === $user->id,
            default => false,
        };
    }
}
