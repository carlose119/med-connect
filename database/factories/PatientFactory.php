<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        $user = User::factory()->create(['role' => 'patient']);

        return [
            'user_id' => $user->id,
            'identification_number' => 'DNI-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999999), 8, '0', STR_PAD_LEFT),
            'phone' => $this->faker->optional(0.8)->e164PhoneNumber(),
            'birth_date' => $this->faker->dateTimeBetween('-80 years', '-1 year')->format('Y-m-d'),
            'gender' => $this->faker->randomElement(['male', 'female', 'other', null]),
        ];
    }
}
