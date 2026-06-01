<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('rejects a second appointment with the same (doctor_id, start_time) for a non-cancelled row', function () {
    // Sanity guard: the appointments table must exist after migrations run.
    expect(Schema::hasTable('appointments'))->toBeTrue(
        'appointments table is missing — the migration that creates it must run before this test'
    );

    // Seed minimum FK targets directly (factories are added later in this PR).
    DB::table('users')->insert([
        'name' => 'Dr. Test', 'email' => 'doc.test@example.test', 'password' => 'x', 'role' => 'doctor', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $doctorId = (int) DB::getPdo()->lastInsertId();

    DB::table('specialties')->insert([
        'name' => 'Cardiology', 'slug' => 'cardiology', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $specialtyId = (int) DB::getPdo()->lastInsertId();

    DB::table('doctors')->insert([
        'user_id' => $doctorId, 'specialty_id' => $specialtyId, 'license_number' => 'LIC-001', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $doctorRowId = (int) DB::getPdo()->lastInsertId();

    DB::table('users')->insert([
        'name' => 'Pat Test', 'email' => 'pat.test@example.test', 'password' => 'x', 'role' => 'patient', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $patientUserId = (int) DB::getPdo()->lastInsertId();

    DB::table('patients')->insert([
        'user_id' => $patientUserId, 'identification_number' => 'DNI-001', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $patientId = (int) DB::getPdo()->lastInsertId();

    $start = '2030-01-15 10:00:00';
    $end = '2030-01-15 10:30:00';

    // First insert must succeed.
    DB::table('appointments')->insert([
        'doctor_id' => $doctorRowId,
        'patient_id' => $patientId,
        'start_time' => $start,
        'end_time' => $end,
        'state' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Second insert with the same (doctor_id, start_time) MUST be rejected.
    $secondInsertFailed = false;
    try {
        DB::table('appointments')->insert([
            'doctor_id' => $doctorRowId,
            'patient_id' => $patientId,
            'start_time' => $start,
            'end_time' => $end,
            'state' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (QueryException $e) {
        $secondInsertFailed = true;
    }

    expect($secondInsertFailed)->toBeTrue(
        'appointments table accepted a duplicate (doctor_id, start_time) — the unique index is missing'
    );
});
