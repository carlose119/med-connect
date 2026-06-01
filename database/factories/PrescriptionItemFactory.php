<?php

namespace Database\Factories;

use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrescriptionItem>
 */
class PrescriptionItemFactory extends Factory
{
    protected $model = PrescriptionItem::class;

    public function definition(): array
    {
        return [
            'prescription_id' => Prescription::factory(),
            'name' => $this->faker->randomElement([
                'Ibuprofen', 'Amoxicillin', 'Paracetamol', 'Loratadine',
                'Omeprazole', 'Metformin', 'Atorvastatin',
            ]),
            'dosage' => $this->faker->randomElement(['200 mg', '400 mg', '500 mg', '20 mg']),
            'frequency' => $this->faker->randomElement(['cada 8h', 'cada 12h', '1 vez al día', '2 veces al día']),
            'duration' => $this->faker->randomElement(['5 días', '7 días', '14 días', '30 días']),
            'position' => 1,
        ];
    }
}
