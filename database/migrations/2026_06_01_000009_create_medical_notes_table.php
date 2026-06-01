<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_history_id')->constrained('medical_histories')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->restrictOnDelete();
            $table->unsignedBigInteger('corrects_note_id')->nullable();
            $table->text('symptoms')->nullable();
            $table->text('physical_exam')->nullable();
            $table->text('diagnosis');
            $table->text('treatment_notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('corrects_note_id')->references('id')->on('medical_notes')->nullOnDelete();
            $table->index(['medical_history_id', 'created_at'], 'medical_notes_history_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_notes');
    }
};
