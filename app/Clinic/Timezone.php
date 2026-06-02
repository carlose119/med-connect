<?php

namespace App\Clinic;

use App\Exceptions\InvalidTimezoneException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Immutable IANA timezone value object.
 *
 * Resolved per-request by App\Http\Middleware\ResolveTimezone and
 * stored on the request's attribute bag. Downstream code (API
 * Resources, datetime-accepting FormRequests, the slot service) reads
 * the resolved instance from there and formats / converts datetimes
 * through it.
 *
 * The object is pure: it does not read from the database, does not
 * hold a static cache, and is safe to share across the request. All
 * time conversion delegates to Carbon, which is the only place the
 * IANA database is consulted.
 */
final readonly class Timezone
{
    public function __construct(public string $name)
    {
    }

    /**
     * Validate the given identifier and return a Timezone instance.
     * Throws InvalidTimezoneException when the name is not a
     * recognised IANA identifier.
     */
    public static function from(string $name): self
    {
        if (! self::isValid($name)) {
            throw new InvalidTimezoneException($name);
        }

        return new self($name);
    }

    /**
     * Cheap check against PHP's bundled IANA list. Used by
     * ResolveTimezone middleware to gate the per-request override
     * without throwing when the override is absent.
     */
    public static function isValid(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        return in_array($name, timezone_identifiers_list(), true);
    }

    /**
     * Convert a UTC Carbon instance into the resolved timezone.
     * The returned CarbonImmutable is the canonical display form
     * for datetimes coming out of the database.
     */
    public function toLocal(CarbonInterface $utc): CarbonImmutable
    {
        return CarbonImmutable::instance($utc)->setTimezone($this->name);
    }

    /**
     * Convert a Carbon instance in the resolved timezone back to UTC.
     * The returned CarbonImmutable is what the action layer expects
     * (the database column is always UTC).
     */
    public function toUtc(CarbonInterface $local): CarbonImmutable
    {
        return CarbonImmutable::instance($local)->setTimezone('UTC');
    }

    /**
     * ISO 8601 with the resolved offset. This is the wire format
     * the API emits for every datetime field.
     */
    public function format(CarbonInterface $utc): string
    {
        return $this->toLocal($utc)->toIso8601String();
    }
}
