<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * tbl_notifications — capstone Section 3.4 (ER Diagram).
     *
     * Columns:
     *   notification_id (PK)        -> id
     *   incident_id (FK)            -> incident_id
     *   user_id (FK -> users)       -> user_id  (recipient of the notification)
     *   message (text)              -> message
     *   is_read (bool)              -> is_read
     *   created_by, updated_by      -> audit columns
     *   created_at, updated_at      -> timestamps
     */
    public function up(): void
    {
        Schema::create('tbl_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('tbl_incidents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });

        DB::statement('ALTER TABLE tbl_notifications ADD CONSTRAINT fk_notifications_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE tbl_notifications ADD CONSTRAINT fk_notifications_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_notifications');
    }
};
