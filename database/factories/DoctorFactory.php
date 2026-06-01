<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        $user = User::factory()->create(['role' => 'doctor']);

        return [
            'user_id' => $user->id,
            'specialty_id' => Specialty::factory(),
            'license_number' => 'LIC-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'bio' => $this->faker->optional(0.7)->paragraph(),
        ];
    }
}
