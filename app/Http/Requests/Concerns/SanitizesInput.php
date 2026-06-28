<?php

namespace App\Http\Requests\Concerns;

trait SanitizesInput
{
    protected function cleanString(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return trim(strip_tags($value));
    }

    protected function cleanNullableString(mixed $value): mixed
    {
        $value = $this->cleanString($value);

        return $value === '' ? null : $value;
    }

    protected function cleanEmail(mixed $value): mixed
    {
        $value = $this->cleanNullableString($value);

        return is_string($value) ? mb_strtolower($value) : $value;
    }

    protected function cleanNumeric(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim(str_replace(',', '.', $value));

        return $value === '' ? null : $value;
    }
}
