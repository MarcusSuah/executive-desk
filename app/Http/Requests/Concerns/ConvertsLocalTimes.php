<?php

namespace App\Http\Requests\Concerns;

use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

/**
 * For form requests that accept wall-clock times ("Y-m-d\TH:i") together with an IANA
 * "timezone" field. Times are converted to UTC using that zone, never the browser's.
 */
trait ConvertsLocalTimes
{
    /**
     * Add an error for any local time that does not exist in the chosen zone (DST gaps).
     *
     * @param  array<int, string>  $fields
     */
    protected function validateLocalTimesExist(Validator $validator, array $fields): void
    {
        $timezone = $this->input('timezone');

        if (! is_string($timezone) || ! in_array($timezone, Timezones::identifiers(), true)) {
            return;
        }

        foreach ($fields as $field) {
            $value = $this->input($field);

            if (! is_string($value) || $value === '' || $validator->errors()->has($field)) {
                continue;
            }

            if (! Timezones::localTimeExists($value, $timezone)) {
                $validator->errors()->add($field, "This time does not exist in {$timezone} because of a daylight-saving change. Choose a different time.");
            }
        }
    }

    /**
     * Convert a validated local time field to UTC.
     */
    protected function utc(string $field): ?CarbonImmutable
    {
        $value = $this->input($field);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Timezones::localToUtc($value, (string) $this->input('timezone'));
    }

    /**
     * Get the validation rule for a local date-time input.
     *
     * @return array<int, string>
     */
    protected function localTimeRule(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'date_format:'.Timezones::LOCAL_FORMAT];
    }
}
