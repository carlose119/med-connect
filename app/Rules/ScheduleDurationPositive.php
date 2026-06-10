<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ScheduleDurationPositive implements Rule
{
    public function passes($attribute, $value): bool
    {
        return is_numeric($value) && (int) $value > 0;
    }

    public function message(): string
    {
        return 'The slot duration must be greater than 0.';
    }
}
