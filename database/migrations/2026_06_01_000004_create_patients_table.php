<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('identification_number');
            $table->string('phone', 32)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 16)->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->unique('identification_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
