<?php

namespace App\Http\Responses\Api;

use App\Exceptions\Domain\DomainException;
use App\Exceptions\InvalidTimezoneException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Standard JSON error envelope for the agenda-http API.
 *
 * Shape (REQ-API-3):
 *   { "error": { "code": "SLOT_NOT_AVAILABLE", "message": "...", "details"?: {...} } }
 *
 * The matching table is the canonical reference for what maps where;
 * see agenda-http/design.md §6. Domain exceptions are matched by
 * instanceof DomainException; their httpStatus() is the source of
 * truth, and the error.code is the short class name uppercased to
 * snake_case. Anything not in the match falls through to the 500
 * INTERNAL_ERROR catch-all (with the message redacted in
 * non-local environments).
 */
class ErrorResponse
{
    public static function fromException(Throwable $e, Request $request): JsonResponse
    {
        [$status, $code, $message, $details] = self::resolve($e, $request);

        $payload = [
            'code' => $code,
            'message' => $message,
        ];
        if ($details !== null) {
            $payload['details'] = $details;
        }

        return new JsonResponse(['error' => $payload], $status);
    }

    /**
     * @return array{0:int,1:string,2:string,3:array<string,mixed>|null}
     */
    private static function resolve(Throwable $e, Request $request): array
    {
        // Specific: tz validation runs before the generic DomainException
        // arm because it is not a DomainException subclass.
        if ($e instanceof InvalidTimezoneException) {
            return [422, 'INVALID_TIMEZONE', $e->getMessage(), [
                'rejected' => $e->getRejectedName(),
            ]];
        }

        if ($e instanceof ValidationException) {
            return [422, 'VALIDATION_ERROR', 'The given data was invalid.', $e->errors()];
        }

        if ($e instanceof AuthenticationException) {
            // REQ-API-1 scenario 3: a Sanctum token whose expires_at is
            // in the past must be reported as TOKEN_EXPIRED (not
            // UNAUTHENTICATED) so clients can distinguish "your session
            // is over" from "you never sent a token". Inspect
            // personal_access_tokens directly via Sanctum's
            // PersonalAccessToken::findToken() (which does NOT go
            // through the auth guard, so it does not re-throw).
            $bearer = $request->bearerToken();
            if (is_string($bearer) && $bearer !== '') {
                $token = PersonalAccessToken::findToken($bearer);
                if ($token !== null && $token->expires_at !== null && $token->expires_at->isPast()) {
                    return [401, 'TOKEN_EXPIRED', 'Token has expired.', null];
                }
            }

            return [401, 'UNAUTHENTICATED', 'Authentication required.', null];
        }

        if ($e instanceof AuthorizationException) {
            return [403, 'FORBIDDEN', 'You are not authorised to perform this action.', null];
        }

        if ($e instanceof ModelNotFoundException) {
            return [404, 'NOT_FOUND', 'The requested resource was not found.', null];
        }

        // Laravel's Handler::prepareException() transforms both
        //   ModelNotFoundException    -> NotFoundHttpException
        //   AuthorizationException    -> AccessDeniedHttpException
        // before render callbacks run, but the original exception is
        // preserved on getPrevious(). Recover the original so the
        // response code distinguishes the two 404 / 403 cases.
        if ($e instanceof NotFoundHttpException && $e->getPrevious() instanceof ModelNotFoundException) {
            return [404, 'NOT_FOUND', 'The requested resource was not found.', null];
        }

        if ($e instanceof AccessDeniedHttpException && $e->getPrevious() instanceof AuthorizationException) {
            return [403, 'FORBIDDEN', 'You are not authorised to perform this action.', null];
        }

        if ($e instanceof NotFoundHttpException) {
            return [404, 'ROUTE_NOT_FOUND', 'The requested endpoint does not exist.', null];
        }

        if ($e instanceof DomainException) {
            return [
                $e->httpStatus(),
                self::classNameToCode($e),
                $e->getMessage(),
                null,
            ];
        }

        // 500 catch-all. Redact the message in non-local environments
        // so internal stack traces / SQL fragments never leak.
        $isLocal = in_array(config('app.env'), ['local', 'testing'], true);
        $message = $isLocal
            ? $e->getMessage()
            : 'An internal error occurred. Please try again or contact support.';

        return [500, 'INTERNAL_ERROR', $message, null];
    }

    /**
     * Derive the error.code from a DomainException's short class name
     * (e.g. SlotNotAvailableException -> SLOT_NOT_AVAILABLE). The
     * convention matches the agenda-core design and is enforced by
     * the table in design.md §6.
     */
    private static function classNameToCode(Throwable $e): string
    {
        $short = (new \ReflectionClass($e))->getShortName();

        // Strip the trailing "Exception" suffix.
        if (str_ends_with($short, 'Exception')) {
            $short = substr($short, 0, -strlen('Exception'));
        }

        // CamelCase to SNAKE_CASE.
        $snake = strtolower((string) preg_replace('/(?<!^)([A-Z])/', '_$1', $short));

        return strtoupper($snake);
    }
}
