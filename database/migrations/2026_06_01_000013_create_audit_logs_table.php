<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 64);
            $table->string('action', 96);
            $table->string('subject_type', 96);
            $table->unsignedBigInteger('subject_id');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            // intentionally no updated_at: audit rows are immutable once written

            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_idx');
            $table->index(['actor_type', 'user_id'], 'audit_logs_actor_idx');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
