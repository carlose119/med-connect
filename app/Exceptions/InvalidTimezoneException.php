<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by App\Clinic\Timezone::from() and by
 * App\Http\Middleware\ResolveTimezone when a user-supplied timezone
 * identifier is not on PHP's IANA list. Lives outside
 * App\Exceptions\Domain because it is a request-validation failure
 * (not a domain rule violation) and maps to a 422 with
 * error.code = 'INVALID_TIMEZONE' in the API error envelope.
 */
class InvalidTimezoneException extends RuntimeException
{
    public function __construct(
        public readonly string $rejectedName,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? sprintf('"%s" is not a valid IANA timezone identifier.', $rejectedName),
        );
    }

    public function getRejectedName(): string
    {
        return $this->rejectedName;
    }
}
