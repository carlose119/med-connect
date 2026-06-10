<?php

namespace App\Actions\Medical;

use App\Models\Prescription;

/**
 * Generate a unique prescription code in the format RX-YYYY-NNNNNN.
 *
 * Collision-resistant: retries up to 5 times if a generated code
 * already exists in the database before giving up.
 */
class GeneratePrescriptionCodeAction
{
    private const MAX_ATTEMPTS = 5;

    public function generate(): string
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $code = $this->makeCode();

            // Check if code already exists (fast path before attempting DB write)
            $exists = Prescription::where('unique_code', $code)->exists();

            if (! $exists) {
                return $code;
            }

            // If we hit MAX_ATTEMPTS, throw — caller must handle
            if ($attempt === self::MAX_ATTEMPTS) {
                throw new \RuntimeException(
                    "Failed to generate a unique prescription code after " . self::MAX_ATTEMPTS . " attempts."
                );
            }
        }

        // This line is unreachable but satisfies static analysis
        throw new \RuntimeException("Code generation loop exited unexpectedly.");
    }

    private function makeCode(): string
    {
        $year = date('Y');
        $digits = (string) random_int(100000, 999999);

        return "RX-{$year}-{$digits}";
    }
}