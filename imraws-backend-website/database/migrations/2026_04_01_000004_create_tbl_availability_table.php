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
     * tbl_availability — capstone Section 3.4 (ER Diagram)
     * plus Section 3.5 Evaluation Q5 explicit status values:
     *   "Available, On Duty, Unavailable, On Break"
     *
     * Columns:
     *   availability_id (PK)          -> id
     *   staff_id (FK -> users, unique)-> staff_id
     *   status (enum)                 -> status
     *     (available | on_duty | unavailable | on_break)
     *   created_by, updated_by        -> audit columns
     *   created_at, updated_at        -> timestamps
     */
    public function up(): void
    {
        DB::statement("CREATE TYPE availability_status AS ENUM ('available', 'on_duty', 'unavailable', 'on_break')");

        Schema::create('tbl_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE tbl_availability ADD COLUMN status availability_status NOT NULL DEFAULT \'unavailable\'');
        DB::statement('ALTER TABLE tbl_availability ADD CONSTRAINT fk_availability_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE tbl_availability ADD CONSTRAINT fk_availability_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('CREATE INDEX tbl_availability_status_idx ON tbl_availability(status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_availability');
        DB::statement('DROP TYPE IF EXISTS availability_status');
    }
};
