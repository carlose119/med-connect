<?php

namespace App\Models;

use Database\Factories\DoctorScheduleOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorScheduleOverride extends Model
{
    /** @use HasFactory<DoctorScheduleOverrideFactory> */
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'date',
        'type',
        'start_time',
        'end_time',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
