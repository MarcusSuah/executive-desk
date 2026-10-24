<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PresentationStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case InPreparation = 'in_preparation';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * Get the statuses for presentations that still need preparation work.
     *
     * @return array<int, string>
     */
    public static function active(): array
    {
        return [self::Draft->value, self::InPreparation->value, self::UnderReview->value];
    }
}
