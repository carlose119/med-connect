<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\MedicalHistory;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalHistory>
 */
class MedicalHistoryFactory extends Factory
{
    protected $model = MedicalHistory::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'primary_doctor_id' => Doctor::factory(),
            'opened_at' => now(),
        ];
    }
}
