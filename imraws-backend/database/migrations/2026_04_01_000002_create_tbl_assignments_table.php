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
     * tbl_assignments — capstone Section 3.4 (ER Diagram).
     *
     * Columns:
     *   assignment_id (PK)            -> id
     *   incident_id (FK)              -> incident_id
     *   team_leader_id (FK -> users)  -> team_leader_id
     *   engineer_review_id (FK -> users, nullable) -> engineer_review_id
     *   assigned_at                   -> assigned_at
     *   resolution_notes              -> resolution_notes
     *   action_status (enum)          -> action_status
     *                                 (pending | accept | reject | correct |
     *                                  override | reassign | assigned | in_progress | resolved)
     *   created_by, updated_by        -> audit columns
     *   created_at, updated_at        -> timestamps
     */
    public function up(): void
    {
        DB::statement("CREATE TYPE assignment_action AS ENUM ('pending', 'accept', 'reject', 'correct', 'override', 'reassign', 'assigned', 'in_progress', 'resolved')");

        Schema::create('tbl_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('tbl_incidents')->cascadeOnDelete();
            $table->foreignId('team_leader_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('engineer_review_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('team_leader_id');
            $table->index('engineer_review_id');
        });

        DB::statement('ALTER TABLE tbl_assignments ADD COLUMN action_status assignment_action NOT NULL DEFAULT \'assigned\'');
        DB::statement('ALTER TABLE tbl_assignments ADD CONSTRAINT fk_assignments_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE tbl_assignments ADD CONSTRAINT fk_assignments_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('CREATE INDEX tbl_assignments_action_status_idx ON tbl_assignments(action_status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_assignments');
        DB::statement('DROP TYPE IF EXISTS assignment_action');
    }
};
