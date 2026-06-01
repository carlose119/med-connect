<?php

namespace App\Models;

use Database\Factories\MedicalNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Append-only by design. The migration has no `updated_at` column and this
 * model does not use the standard Eloquent `Model::update()` flow. Amendments
 * are persisted as a NEW row whose `corrects_note_id` references the prior
 * note; the original row is never mutated. Wire-up of the `amend()` factory
 * method lives in a later change.
 */
class MedicalNote extends Model
{
    /** @use HasFactory<MedicalNoteFactory> */
    use HasFactory;

    protected $fillable = [
        'medical_history_id',
        'appointment_id',
        'doctor_id',
        'corrects_note_id',
        'symptoms',
        'physical_exam',
        'diagnosis',
        'treatment_notes',
    ];

    public $timestamps = false;

    public function medicalHistory(): BelongsTo
    {
        return $this->belongsTo(MedicalHistory::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function corrects(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_note_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'corrects_note_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MedicalAttachment::class);
    }
}
