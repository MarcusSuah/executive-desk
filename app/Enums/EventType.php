<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EventType: string
{
    use HasOptions;

    case InternalMeeting = 'internal_meeting';
    case ExternalMeeting = 'external_meeting';
    case BusinessAppointment = 'business_appointment';
    case Interview = 'interview';
    case Presentation = 'presentation';
    case Travel = 'travel';
    case Personal = 'personal';
    case Deadline = 'deadline';
}
