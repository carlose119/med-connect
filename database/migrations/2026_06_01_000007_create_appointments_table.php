<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->dateTime('start_time'); // stored in UTC by convention
            $table->dateTime('end_time');
            $table->string('state', 32)->default('pending');
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'start_time'], 'appointments_patient_start_idx');
            $table->index(['doctor_id', 'start_time', 'state'], 'appointments_doctor_start_state_idx');
        });

        // Driver-aware unique index: at most one non-cancelled row per (doctor_id, start_time).
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // MariaDB/MySQL: virtual generated column populated only when state = 'cancelled'.
            // Cancelled rows carry id; non-cancelled rows carry NULL. NULLs are not unique-constrained.
            DB::statement(<<<'SQL'
                ALTER TABLE appointments
                ADD COLUMN cancelled_marker BIGINT UNSIGNED
                GENERATED ALWAYS AS (CASE WHEN state = 'cancelled' THEN id ELSE NULL END) STORED,
                ADD UNIQUE KEY uniq_doctor_start_not_cancelled (doctor_id, start_time, cancelled_marker)
            SQL);
        } else {
            // PostgreSQL/SQLite: partial unique index. Both engines support CREATE UNIQUE INDEX ... WHERE.
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX uniq_doctor_start_not_cancelled
                ON appointments (doctor_id, start_time)
                WHERE state <> 'cancelled'
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
