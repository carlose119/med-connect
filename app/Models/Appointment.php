<?php

namespace App\Models;

use App\States\Appointment\AppointmentState as AppointmentStateClass;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStates\HasStates;

/**
 * Appointment aggregate. State lifecycle is modelled with
 * spatie/laravel-model-states 2.13 on top of a PHP backed enum and 5
 * concrete state classes; the cast `state => AppointmentState::class`
 * is to the abstract state class (which has the `config()` map of
 * allowed transitions). The DB column itself is still a string —
 * spatie persists the morph name (e.g. 'pending') and resolves the
 * concrete state class from the mapping in the abstract.
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory, HasStates;

    protected $fillable = [
        'doctor_id',
        'patient_id',
        'start_time',
        'end_time',
        'state',
        'cancellation_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'state' => AppointmentStateClass::class,
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function medicalNotes(): HasMany
    {
        return $this->hasMany(MedicalNote::class);
    }
}
