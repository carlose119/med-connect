<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only by design. The migration has no `updated_at` column and this
 * model does not expose any public mutator beyond the factory's `create()`.
 * Reads are unrestricted; writes through Eloquent are reserved for trusted
 * server-side code paths that emit audit rows as a side effect of an admin
 * action.
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    protected static function boot(): void
    {
        parent::boot();

        static::saving(static function (AuditLog $log): void {
            // Allow create (model does not exist yet), reject update
            // (model already exists — append-only contract).
            if ($log->exists) {
                throw new \LogicException('Audit logs are immutable. Update is not permitted.');
            }
        });

        static::deleting(static function (AuditLog $log): void {
            throw new \LogicException('Audit logs are immutable. Delete is not permitted.');
        });
    }

    protected $fillable = [
        'user_id',
        'actor_type',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
