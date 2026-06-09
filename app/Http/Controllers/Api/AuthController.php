<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Auth surface for the agenda-http API (REQ-API-7 auth scenarios).
 *
 *   login()  → POST   /api/auth/login    (public; returns {user, token})
 *   logout() → POST   /api/auth/logout   (auth; revokes current token; 204)
 *   me()     → GET    /api/auth/me       (auth; returns UserResource)
 *
 * The login route is registered OUTSIDE the `auth:sanctum` group but
 * INSIDE the `ResolveTimezone` group (design N7: 401 must also be
 * rendered in the requested TZ; here the TZ is purely cosmetic on
 * the response because the request body has no datetime).
 *
 * `login()` calls `Auth::guard('web')->attempt()` (the default
 * `web` guard with the `App\Models\User` provider) and maps a
 * failed attempt to `AuthenticationException` so the PR 1
 * exception handler renders the standard 401 UNAUTHENTICATED
 * envelope. The `web` guard's session driver is unused for token
 * issuance — we only call `attempt()` for credential validation,
 * then `User::createToken()` for the Sanctum bearer token.
 *
 * `logout()` deletes only the current access token (not all the
 * user's tokens) so a client with multiple devices can log out of
 * one device without disrupting the others.
 */
class AuthController extends Controller
{
    /**
     * POST /api/auth/login — exchange credentials for a Sanctum token.
     *
     * Wire shape (REQ-API-7 scenario 1):
     *   200 { "data": { "user": <UserResource>, "token": "<plaintext>" } }
     *
     * Failure (bad creds or unknown email) maps to 401 UNAUTHENTICATED
     * via the `AuthenticationException` -> ErrorResponse contract.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = [
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ];

        if (! Auth::guard('web')->attempt($credentials)) {
            throw new AuthenticationException;
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        // `device_name` is optional; default to a generic label so
        // the personal_access_tokens row is never NULL.
        $deviceName = (string) ($request->validated('device_name') ?? 'api-client');

        $token = $user->createToken($deviceName)->plainTextToken;

        return new JsonResponse([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ]);
    }

    /**
     * POST /api/auth/logout — revoke the current Sanctum token.
     *
     * Wire shape (REQ-API-7 scenario 3):
     *   204 (empty body)
     *
     * Only the current access token is deleted so a multi-device
     * client can log out of one device without disrupting others.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * GET /api/auth/me — return the current authenticated user.
     *
     * Wire shape (REQ-API-7 scenario 4 + REQ-API-1 scenario 2):
     *   200 { "data": <UserResource> }
     *
     * Replaces the PR 1 placeholder at `/api/me` (which is retired
     * in T-API-46). The middleware group still gates the route; an
     * unauthenticated request returns 401 UNAUTHENTICATED via the
     * PR 1 exception handler.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
