<?php

namespace App\Models;

use Database\Factories\MedicalHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalHistory extends Model
{
    /** @use HasFactory<MedicalHistoryFactory> */
    use HasFactory;

    protected $fillable = ['patient_id', 'primary_doctor_id', 'opened_at'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function primaryDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'primary_doctor_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(MedicalNote::class);
    }
}
