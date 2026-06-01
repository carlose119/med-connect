<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('primary_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamps();

            $table->unique('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_histories');
    }
};
