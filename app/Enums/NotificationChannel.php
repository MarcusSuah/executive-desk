<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum NotificationChannel: string
{
    use HasOptions;

    case Mail = 'mail';
    case Sms = 'sms';
    case Push = 'push';

    /**
     * Get a human-readable label for the channel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Mail => 'Email',
            self::Sms => 'SMS',
            self::Push => 'Push',
        };
    }
}
