<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedule_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['block', 'extra_availability']);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['doctor_id', 'date'], 'doctor_overrides_doctor_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_schedule_overrides');
    }
};
