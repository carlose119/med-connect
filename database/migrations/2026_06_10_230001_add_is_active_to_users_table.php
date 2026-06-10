<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
        });

        // Note: softDeletes() cannot be added via Schema if the model
        // doesn't use Illuminate\Database\Eloquent\SoftDeletes yet.
        // The controller also needs to check $user->is_active before login.
        // See Authenticate.php middleware for the active check.
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};