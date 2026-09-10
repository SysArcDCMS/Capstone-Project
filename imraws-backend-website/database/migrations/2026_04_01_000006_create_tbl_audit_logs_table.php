<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * tbl_audit_logs — capstone Section 3.4 (ER Diagram).
     * "Provides a complete and tamper-evident record of all significant
     *  system events and user actions performed within the platform."
     *
     * Columns:
     *   audit_log_id (PK) -> id
     *   user_id (FK -> users)  -> user_id
     *   action (string)        -> action  (login, create, update, delete, etc.)
     *   table_name (string)    -> table_name
     *   record_id (bigint)     -> record_id
     *   old_value (json, null) -> old_value
     *   new_value (json, null) -> new_value
     *   created_at (timestamp) -> created_at
     */
    public function up(): void
    {
        Schema::create('tbl_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('table_name', 64);
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['table_name', 'record_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_audit_logs');
    }
};
