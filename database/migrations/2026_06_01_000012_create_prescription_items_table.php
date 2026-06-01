<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->string('name');
            $table->string('dosage', 128)->nullable();
            $table->string('frequency', 128)->nullable();
            $table->string('duration', 128)->nullable();
            $table->unsignedInteger('position');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['prescription_id', 'position'], 'prescription_items_prescription_position_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
