<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_note_id')->constrained('medical_notes')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('medical_note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_attachments');
    }
};
