<?php

namespace Database\Factories;

use App\Models\MedicalAttachment;
use App\Models\MedicalNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalAttachment>
 */
class MedicalAttachmentFactory extends Factory
{
    protected $model = MedicalAttachment::class;

    public function definition(): array
    {
        $ext = $this->faker->randomElement(['pdf', 'jpg', 'png']);
        $name = $this->faker->slug(3).'.'.$ext;

        return [
            'medical_note_id' => MedicalNote::factory(),
            'file_path' => 'attachments/'.$name,
            'file_name' => $name,
            'mime_type' => match ($ext) {
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
            },
            'size_bytes' => $this->faker->numberBetween(1024, 5_000_000),
            'uploaded_by' => User::factory(),
        ];
    }
}
