<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'device_name',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Find a token by its plain-text value (hashed comparison).
     */
    public static function findByToken(string $plainToken): ?self
    {
        $hashed = hash('sha256', $plainToken);

        return static::where('token', $hashed)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Revoke all tokens for a user (used on logout or full refresh).
     */
    public static function revokeAllForUser(int $userId): void
    {
        static::where('user_id', $userId)->delete();
    }
}