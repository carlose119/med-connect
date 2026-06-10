<?php

use App\Rules\ScheduleDurationPositive;

uses(Tests\TestCase::class);

// ─── passes() ──────────────────────────────────────────────────────

it('passes for positive integer values', function (int $value): void {
    $rule = new ScheduleDurationPositive;

    $passes = $rule->passes('slot_duration_minutes', $value);

    expect($passes)->toBeTrue("Value $value should pass");
})->with([1, 15, 30, 60, 120, 1440]);

// ─── fails() ───────────────────────────────────────────────────────

it('fails for zero', function (): void {
    $rule = new ScheduleDurationPositive;

    expect($rule->passes('slot_duration_minutes', 0))->toBeFalse();
    expect($rule->message())->toBe('The slot duration must be greater than 0.');
});

it('fails for negative values', function (int $value): void {
    $rule = new ScheduleDurationPositive;

    expect($rule->passes('slot_duration_minutes', $value))->toBeFalse();
    expect($rule->message())->toBe('The slot duration must be greater than 0.');
})->with([-1, -30]);

it('fails for non-numeric strings', function (): void {
    $rule = new ScheduleDurationPositive;

    expect($rule->passes('slot_duration_minutes', 'abc'))->toBeFalse();
    expect($rule->message())->toBe('The slot duration must be greater than 0.');
});

it('fails for null', function (): void {
    $rule = new ScheduleDurationPositive;

    expect($rule->passes('slot_duration_minutes', null))->toBeFalse();
    expect($rule->message())->toBe('The slot duration must be greater than 0.');
});

it('fails for empty string', function (): void {
    $rule = new ScheduleDurationPositive;

    expect($rule->passes('slot_duration_minutes', ''))->toBeFalse();
    expect($rule->message())->toBe('The slot duration must be greater than 0.');
});
