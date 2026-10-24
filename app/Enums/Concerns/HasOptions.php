<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /**
     * Get the enum cases as value/label pairs for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * Get all enum values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Get a human-readable label for the case.
     */
    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
