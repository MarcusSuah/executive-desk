<?php

namespace App\Services\Notifications;

use Illuminate\Support\Str;

/**
 * Strips credentials and personal data from provider error messages before they are stored or shown.
 */
class ErrorSanitizer
{
    /**
     * Sanitize an error message.
     */
    public static function clean(?string $message): ?string
    {
        if ($message === null || trim($message) === '') {
            return null;
        }

        $patterns = [
            '/(password|passwd|pwd|secret|token|api[_-]?key|authorization|auth)\s*[=:]\s*\S+/i' => '$1=[redacted]',
            '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i' => 'Bearer [redacted]',
            '/https?:\/\/\S+/i' => '[url]',
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i' => '[email]',
            '/\b(?:[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,}(?::\d+)?\b/i' => '[host]',
            '/\b\d{1,3}(?:\.\d{1,3}){3}(?::\d+)?\b/' => '[host]',
            '/\+?\d[\d\s\-]{7,}\d/' => '[number]',
            '/[A-Za-z0-9+\/_\-]{32,}={0,2}/' => '[redacted]',
        ];

        $clean = preg_replace(array_keys($patterns), array_values($patterns), $message) ?? '';

        return Str::limit(trim(preg_replace('/\s+/', ' ', $clean) ?? ''), 500);
    }
}
