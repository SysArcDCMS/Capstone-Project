<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the auditor column to `users` so admin actions that toggle
     * is_active (DFD 1.7 — Deactivate/Reactivate User Account) can record
     * who performed them, mirroring the created_by/updated_by audit
     * columns already present on the other capstone tables.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('updated_by')->nullable()->after('updated_at');
            $table->index('updated_by');
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT fk_users_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('fk_users_updated_by');
            $table->dropIndex(['updated_by']);
            $table->dropColumn('updated_by');
        });
    }
};