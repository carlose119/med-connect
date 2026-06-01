<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Idempotent base seed: one admin, one specialty, one doctor with a recurring
     * Monday rule, one patient, and one pending appointment that exercises the
     * doctor + patient + appointment chain end-to-end.
     *
     * `migrate:fresh --seed` produces the same rows every time; `seed` alone is
     * also safe (firstOrCreate on email / slug / dni).
     */
    public function run(): void
    {
        // 1) Admin — firstOrCreate on email so re-seeding is idempotent.
        $admin = User::firstOrCreate(
            ['email' => 'admin@med-connect.test'],
            [
                'name' => 'Med-Connect Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ],
        );

        // 2) Default specialty.
        $specialty = Specialty::firstOrCreate(
            ['slug' => 'general-medicine'],
            ['name' => 'General Medicine', 'is_active' => true],
        );

        // 3) Doctor user + Doctor row. The user is created with role=doctor; the
        //    Doctor row points at the user and the default specialty.
        $doctorUser = User::firstOrCreate(
            ['email' => 'doctor@med-connect.test'],
            [
                'name' => 'Dr. Demo',
                'password' => Hash::make('password'),
                'role' => 'doctor',
                'email_verified_at' => now(),
            ],
        );

        $doctor = Doctor::firstOrCreate(
            ['user_id' => $doctorUser->id],
            [
                'specialty_id' => $specialty->id,
                'license_number' => 'LIC-DEMO-001',
                'bio' => 'Seed doctor used by the demo chain.',
            ],
        );

        // 4) Recurring rule for the doctor: Monday 09:00-12:00 (day_of_week = 1 in ISO-8601).
        DoctorSchedule::firstOrCreate(
            [
                'doctor_id' => $doctor->id,
                'day_of_week' => 1,
                'start_time' => '09:00:00',
            ],
            [
                'end_time' => '12:00:00',
                'slot_duration_minutes' => 30,
                'is_active' => true,
            ],
        );

        // 5) Patient user + Patient row.
        $patientUser = User::firstOrCreate(
            ['email' => 'patient@med-connect.test'],
            [
                'name' => 'Demo Patient',
                'password' => Hash::make('password'),
                'role' => 'patient',
                'email_verified_at' => now(),
            ],
        );

        $patient = Patient::firstOrCreate(
            ['user_id' => $patientUser->id],
            [
                'identification_number' => 'DNI-DEMO-0001',
                'phone' => '+5491100000000',
                'birth_date' => '1990-01-01',
                'gender' => 'other',
            ],
        );

        // 6) One pending appointment that lands 3h into the future from now. The
        //    next Monday is computed so the slot is consistent with the rule above;
        //    if today is Monday and we're past 09:00, fall back to next Monday.
        $start = $this->nextMondayAt(9, 0);
        if ($start->lessThan(now()->addHours(3))) {
            $start = $start->copy()->addWeek();
        }
        $end = $start->copy()->addMinutes(30);

        Appointment::firstOrCreate(
            [
                'doctor_id' => $doctor->id,
                'patient_id' => $patient->id,
                'start_time' => $start->format('Y-m-d H:i:s'),
            ],
            [
                'end_time' => $end->format('Y-m-d H:i:s'),
                'state' => 'pending',
                'notes' => 'Seed appointment for the demo chain.',
            ],
        );

        $this->command?->info(sprintf(
            'Seeded: admin=%d specialty=%d doctor=%d patient=%d',
            $admin->id,
            $specialty->id,
            $doctor->id,
            $patient->id,
        ));
    }

    private function nextMondayAt(int $hour, int $minute): Carbon
    {
        return Carbon::now()
            ->next(Carbon::MONDAY)
            ->setTime($hour, $minute, 0);
    }
}
