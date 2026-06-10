<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ScheduleEndAfterStart implements Rule
{
    public function __construct(
        private readonly string $startTime
    ) {}

    public function passes($attribute, $value): bool
    {
        return $value > $this->startTime;
    }

    public function message(): string
    {
        return 'The end time must be after the start time.';
    }
}
