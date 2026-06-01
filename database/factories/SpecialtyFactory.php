<?php

namespace Database\Factories;

use App\Models\Specialty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Specialty>
 */
class SpecialtyFactory extends Factory
{
    protected $model = Specialty::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'Cardiology', 'Pediatrics', 'Dermatology', 'Neurology',
            'Orthopedics', 'Gynecology', 'Ophthalmology', 'Psychiatry',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.strtolower(Str::random(4)),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
