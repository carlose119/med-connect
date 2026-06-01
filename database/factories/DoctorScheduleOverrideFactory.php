<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\DoctorScheduleOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorScheduleOverride>
 */
class DoctorScheduleOverrideFactory extends Factory
{
    protected $model = DoctorScheduleOverride::class;

    public function definition(): array
    {
        return [
            'doctor_id' => Doctor::factory(),
            'date' => $this->faker->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'type' => 'block',
            'start_time' => '09:15:00',
            'end_time' => '09:45:00',
            'reason' => $this->faker->optional(0.5)->sentence(),
        ];
    }

    public function block(): static
    {
        return $this->state(fn () => ['type' => 'block']);
    }

    public function extraAvailability(): static
    {
        return $this->state(fn () => [
            'type' => 'extra_availability',
            'start_time' => '15:00:00',
            'end_time' => '15:30:00',
            'reason' => null,
        ]);
    }

    public function fullDay(): static
    {
        return $this->state(fn () => [
            'type' => 'block',
            'start_time' => null,
            'end_time' => null,
        ]);
    }
}
