<?php

namespace App\Traits;

/**
 * Generate a unique prescription code in the format RX-YYYY-NNNNNN.
 *
 * Uses 6 random digits (100000–999999). Uniqueness is not guaranteed
 * by the generator alone — the DB unique constraint on `unique_code`
 * acts as the last line of defence.
 */
trait GeneratesPrescriptionCode
{
    public static function generateUniqueCode(): string
    {
        $year = date('Y');
        $digits = (string) random_int(100000, 999999);

        return "RX-{$year}-{$digits}";
    }
}