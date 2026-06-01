<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\MedicalHistory;
use App\Models\MedicalNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalNote>
 */
class MedicalNoteFactory extends Factory
{
    protected $model = MedicalNote::class;

    public function definition(): array
    {
        return [
            'medical_history_id' => MedicalHistory::factory(),
            'appointment_id' => null,
            'doctor_id' => Doctor::factory(),
            'corrects_note_id' => null,
            'symptoms' => $this->faker->optional(0.8)->sentence(),
            'physical_exam' => $this->faker->optional(0.5)->paragraph(),
            'diagnosis' => $this->faker->sentence(),
            'treatment_notes' => $this->faker->optional(0.6)->paragraph(),
        ];
    }
}
