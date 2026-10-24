<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ReminderKind: string
{
    use HasOptions;

    case EventStart = 'event_start';
    case TaskDue = 'task_due';
    case PresentationPreparation = 'presentation_preparation';
    case PresentationReview = 'presentation_review';
}
