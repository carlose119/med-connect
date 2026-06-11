<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\Patient;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;

/**
 * Auth surface for the agenda-http API (REQ-API-7 auth scenarios).
 *
 *   login()    → POST   /api/auth/login    (public; returns {user, token, refresh_token})
 *   register() → POST   /api/auth/register (public; returns {user, token, refresh_token})
 *   logout()   → POST   /api/auth/logout   (auth; revokes current token + refresh tokens; 204)
 *   me()       → GET    /api/auth/me       (auth; returns UserResource)
 *   refresh()  → POST   /api/auth/refresh  (public; exchange refresh_token for new tokens)
 *
 * `login()` and `register()` generate both a Sanctum access token AND a
 * refresh token (stored in the `refresh_tokens` table, hashed SHA-256,
 * 30-day TTL). The refresh token is returned alongside the access token.
 *
 * `refresh()` validates the incoming refresh token, revokes it, generates
 * a new access token + new refresh token, and returns both. This allows
 * the mobile app to maintain sessions without re-entering credentials.
 *
 * `logout()` deletes the current Sanctum access token AND all refresh
 * tokens for the user so a logout is truly clean across all devices.
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

        $deviceName = (string) ($request->validated('device_name') ?? 'mobile-app');

        $token = $user->createToken($deviceName)->plainTextToken;
        $refreshToken = $this->createRefreshToken($user, $deviceName);

        return new JsonResponse([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'refresh_token' => $refreshToken,
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
        $user = $request->user();

        // Revoke current Sanctum token
        $user->currentAccessToken()?->delete();

        // Revoke all refresh tokens (clean logout across all devices)
        RefreshToken::revokeAllForUser($user->id);

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

    /**
     * POST /api/auth/register — patient self-registration for mobile app.
     *
     * Wire shape:
     *   201 { "data": { "user": <UserResource>, "token": "<plaintext>" } }
     *
     * Validation mirrors the patient-web registration (RF-1.3):
     *   - name: required, string, max 255
     *   - email: required, email, unique:users
     *   - password: required, min 8, confirmed
     *   - identification_number: required, unique:patients
     *   - phone: required, max 20
     *   - birth_date: optional, date (for mobile profile completeness)
     *   - gender: optional, in:male,female,other
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'identification_number' => ['required', 'string', 'max:50', 'unique:patients'],
            'phone' => ['required', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors()->toArray(),
                ],
            ], 422);
        }

        $validated = $validator->validated();

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'role' => 'patient',
                'is_active' => true,
            ]);

            Patient::create([
                'user_id' => $user->id,
                'identification_number' => $validated['identification_number'],
                'phone' => $validated['phone'],
                'birth_date' => $validated['birth_date'] ?? null,
                'gender' => $validated['gender'] ?? null,
            ]);

            return $user;
        });

        $deviceName = (string) ($request->input('device_name') ?? 'mobile-app');
        $token = $user->createToken($deviceName)->plainTextToken;
        $refreshToken = $this->createRefreshToken($user, $deviceName);

        return (new JsonResponse([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'refresh_token' => $refreshToken,
            ],
        ]))->setStatusCode(201);
    }

    /**
     * POST /api/auth/refresh — exchange a refresh token for new access + refresh tokens.
     *
     * Wire shape:
     *   Request:  { "refresh_token": "<plaintext>" }
     *   Response: { "token": "<plaintext>", "refresh_token": "<plaintext>" }
     *
     * On success: the old refresh token is revoked and a new pair is issued.
     * On failure (invalid/expired): returns 401 UNAUTHORIZED.
     */
    public function refresh(Request $request): JsonResponse
    {
        $plainToken = $request->input('refresh_token');

        if (! is_string($plainToken) || blank($plainToken)) {
            throw new AuthenticationException;
        }

        $storedToken = RefreshToken::findByToken($plainToken);

        if (! $storedToken || ! $storedToken->isValid()) {
            throw new AuthenticationException;
        }

        $user = $storedToken->user;
        $deviceName = $storedToken->device_name ?? 'mobile-app';

        // Revoke old refresh token
        $storedToken->delete();

        // Revoke old access token (force re-auth)
        $user->currentAccessToken()?->delete();

        // Issue new pair
        $token = $user->createToken($deviceName)->plainTextToken;
        $newRefreshToken = $this->createRefreshToken($user, $deviceName);

        return new JsonResponse([
            'token' => $token,
            'refresh_token' => $newRefreshToken,
        ]);
    }

    /**
     * Create a hashed refresh token for the given user.
     * Token TTL: 30 days.
     */
    private function createRefreshToken(User $user, string $deviceName): string
    {
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        RefreshToken::create([
            'user_id' => $user->id,
            'token' => $hashedToken,
            'device_name' => $deviceName,
            'expires_at' => now()->addDays(30),
        ]);

        // Keep max 5 refresh tokens per user (remove oldest)
        $count = RefreshToken::where('user_id', $user->id)->count();
        if ($count > 5) {
            $keepIds = RefreshToken::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(5)
                ->pluck('id');
            RefreshToken::where('user_id', $user->id)
                ->whereNotIn('id', $keepIds)
                ->delete();
        }

        return $plainToken;
    }
}
