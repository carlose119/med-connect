<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1=Monday ... 7=Sunday (ISO-8601)
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_duration_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['doctor_id', 'day_of_week', 'is_active'], 'doctor_schedules_doctor_dow_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_schedules');
    }
};
