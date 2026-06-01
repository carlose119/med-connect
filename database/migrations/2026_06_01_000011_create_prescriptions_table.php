<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('unique_code', 64);
            $table->dateTime('issued_at');
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique('unique_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
