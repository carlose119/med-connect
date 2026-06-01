<?php

namespace App\Exceptions\Domain;

use RuntimeException;

/**
 * Base class for the domain's semantic exceptions. Each subclass declares an
 * HTTP status that the exception handler maps to a response. Domain code
 * throws these directly; controllers and middleware do not need to know
 * about them — only the handler does.
 *
 * The base class itself has no HTTP status. Subclasses must implement
 * httpStatus() to declare the response code the handler should emit.
 */
abstract class DomainException extends RuntimeException
{
    /**
     * The HTTP status code the exception handler maps this exception to.
     */
    abstract public function httpStatus(): int;
}
