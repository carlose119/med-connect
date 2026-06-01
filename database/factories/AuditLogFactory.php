<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor_type' => 'admin',
            'action' => $this->faker->randomElement(['create_doctor', 'update_user', 'delete_patient']),
            'subject_type' => 'doctor',
            'subject_id' => $this->faker->numberBetween(1, 1000),
            'metadata' => ['reason' => $this->faker->sentence()],
            'ip_address' => $this->faker->ipv4(),
        ];
    }
}
