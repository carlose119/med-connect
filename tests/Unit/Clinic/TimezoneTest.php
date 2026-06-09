<?php

use App\Clinic\Timezone;
use App\Exceptions\InvalidTimezoneException;
use Carbon\CarbonImmutable;

/**
 * Unit tests for the App\Clinic\Timezone value object.
 *
 * Pure logic — no DB, no HTTP. Per the PR 1 TDD exception documented
 * in tasks.md, the value object and its tests land in the same commit
 * (T-API-3). The integration surface (middleware → request attribute,
 * API Resources reading the attribute) is covered in T-API-5 and
 * T-API-7 with strict red→green.
 */
it('returns true for valid IANA timezones', function (): void {
    expect(Timezone::isValid('America/Argentina/Buenos_Aires'))->toBeTrue();
    expect(Timezone::isValid('UTC'))->toBeTrue();
    expect(Timezone::isValid('America/New_York'))->toBeTrue();
    expect(Timezone::isValid('Europe/Madrid'))->toBeTrue();
});

it('returns false for invalid timezone identifiers', function (): void {
    expect(Timezone::isValid('Atlantis/Mu'))->toBeFalse();
    expect(Timezone::isValid('Not/A/Zone'))->toBeFalse();
    expect(Timezone::isValid('utc'))->toBeFalse();          // IANA is case-sensitive
    expect(Timezone::isValid('Buenos Aires'))->toBeFalse();  // no spaces
    expect(Timezone::isValid(''))->toBeFalse();
});

it('throws InvalidTimezoneException for invalid tz in from()', function (): void {
    try {
        Timezone::from('Not/A/Real/Zone');
        $this->fail('Expected InvalidTimezoneException was not thrown.');
    } catch (InvalidTimezoneException $e) {
        expect($e->getRejectedName())->toBe('Not/A/Real/Zone');
        expect($e->getMessage())->toContain('Not/A/Real/Zone');
    }
});

it('returns a Timezone instance for valid tz in from()', function (): void {
    $tz = Timezone::from('America/Argentina/Buenos_Aires');

    expect($tz)->toBeInstanceOf(Timezone::class);
    expect($tz->name)->toBe('America/Argentina/Buenos_Aires');
});

it('converts UTC to the local timezone with the correct offset', function (): void {
    $tz = Timezone::from('America/Argentina/Buenos_Aires');

    // 2026-06-15T15:00:00Z is 12:00 -03:00 in Buenos Aires (no DST in winter).
    $utc = CarbonImmutable::parse('2026-06-15T15:00:00Z', 'UTC');
    $local = $tz->toLocal($utc);

    expect($local->toIso8601String())->toBe('2026-06-15T12:00:00-03:00');
    expect($local->getTimezone()->getName())->toBe('America/Argentina/Buenos_Aires');
});

it('converts local timezone to UTC', function (): void {
    $tz = Timezone::from('America/Argentina/Buenos_Aires');

    $local = CarbonImmutable::parse('2026-06-15T12:00:00', 'America/Argentina/Buenos_Aires');
    $utc = $tz->toUtc($local);

    expect($utc->toIso8601String())->toBe('2026-06-15T15:00:00+00:00');
    expect($utc->getTimezone()->getName())->toBe('UTC');
});

it('formats a UTC datetime as ISO 8601 with the resolved offset', function (): void {
    $tz = Timezone::from('America/New_York');

    $utc = CarbonImmutable::parse('2026-06-15T15:00:00Z', 'UTC');
    // June is summer in the northern hemisphere → EDT (UTC-4).
    expect($tz->format($utc))->toBe('2026-06-15T11:00:00-04:00');
});
