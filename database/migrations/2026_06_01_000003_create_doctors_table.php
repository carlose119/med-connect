<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained('specialties')->restrictOnDelete();
            $table->string('license_number');
            $table->text('bio')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->unique('license_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
