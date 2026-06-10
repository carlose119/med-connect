<?php

use App\Traits\GeneratesPrescriptionCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('GeneratesPrescriptionCode', function (): void {
    it('returns a code matching the RX-YYYY-NNNNNN format', function (): void {
        $code = GeneratesPrescriptionCode::generateUniqueCode();

        expect($code)->toMatch('/^RX-\d{4}-\d{6}$/');
    });

    it('uses the current year in the code', function (): void {
        $code = GeneratesPrescriptionCode::generateUniqueCode();
        $currentYear = (string) date('Y');

        expect($code)->toContain("RX-{$currentYear}-");
    });

    it('generates unique codes across 100 calls', function (): void {
        $codes = [];

        for ($i = 0; $i < 100; $i++) {
            $codes[] = GeneratesPrescriptionCode::generateUniqueCode();
        }

        expect(array_unique($codes))->toHaveCount(100);
    });

    it('contains exactly 6 digits after the year', function (): void {
        $code = GeneratesPrescriptionCode::generateUniqueCode();

        preg_match('/^RX-\d{4}-(\d{6})$/', $code, $matches);
        expect($matches[1] ?? '')->toHaveLength(6);
        expect((int) $matches[1])->toBeGreaterThanOrEqual(100000);
        expect((int) $matches[1])->toBeLessThanOrEqual(999999);
    });
});