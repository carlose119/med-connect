<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        // Start at least 3 hours in the future so the booking-action 2h window
        // does not filter the slot out under default factory use.
        $start = Carbon::now()->addHours($this->faker->numberBetween(3, 72))
            ->minute(0)->second(0);
        $end = $start->copy()->addMinutes(30);

        return [
            'doctor_id' => Doctor::factory(),
            'patient_id' => Patient::factory(),
            'start_time' => $start->format('Y-m-d H:i:s'),
            'end_time' => $end->format('Y-m-d H:i:s'),
            'state' => 'pending',
            'cancellation_reason' => null,
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['state' => 'pending']);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['state' => 'confirmed']);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['state' => 'completed']);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'state' => 'cancelled',
            'cancellation_reason' => $this->faker->sentence(),
        ]);
    }

    public function noShow(): static
    {
        return $this->state(fn () => ['state' => 'no_show']);
    }
}
