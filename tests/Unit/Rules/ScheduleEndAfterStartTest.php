<?php

use App\Rules\ScheduleEndAfterStart;

uses(Tests\TestCase::class);

// ─── passes() ──────────────────────────────────────────────────────

it('passes when end time is after start time', function (string $start, string $end): void {
    $rule = new ScheduleEndAfterStart($start);

    expect($rule->passes('end_time', $end))->toBeTrue(
        "End $end should be after start $start"
    );
})->with([
    ['09:00:00', '17:00:00'],
    ['00:00:00', '23:59:59'],
]);

// ─── fails() ───────────────────────────────────────────────────────

it('fails when end time is before start time', function (): void {
    $rule = new ScheduleEndAfterStart('17:00:00');

    expect($rule->passes('end_time', '09:00:00'))->toBeFalse();
    expect($rule->message())->toBe('The end time must be after the start time.');
});

it('fails when end time equals start time', function (): void {
    $rule = new ScheduleEndAfterStart('10:00:00');

    expect($rule->passes('end_time', '10:00:00'))->toBeFalse();
    expect($rule->message())->toBe('The end time must be after the start time.');
});
