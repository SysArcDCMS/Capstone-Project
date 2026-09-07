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
     * tbl_feedback — capstone Section 3.4 (ER Diagram).
     * Captures Human-in-the-Loop (HITL) corrections and Engineer adjudications.
     *
     * Columns (verbatim from capstone, mapped to Postgres):
     *   feedback_id (PK)             -> id
     *   incident_id (FK)             -> incident_id
     *   team_leader_id (FK -> users) -> team_leader_id
     *   engineer_id (FK -> users)    -> engineer_id
     *   original_category            -> original_category
     *   original_severity            -> original_severity
     *   composite_score (float, null)-> composite_score
     *   action_taken (enum)          -> action_taken
     *     (accept | reject | correct | override | reassign)
     *   corrected_category           -> corrected_category
     *   corrected_severity           -> corrected_severity
     *   rejection_reason             -> rejection_reason
     *   final_decision               -> final_decision
     *   feedback_timestamp           -> feedback_timestamp
     *   review_timestamp             -> review_timestamp
     *   used_for_training (bool)     -> used_for_training
     *   created_by, updated_by       -> audit columns
     *   created_at, updated_at       -> timestamps
     */
    public function up(): void
    {
        DB::statement("CREATE TYPE feedback_action AS ENUM ('accept', 'reject', 'correct', 'override', 'reassign')");

        Schema::create('tbl_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('tbl_incidents')->cascadeOnDelete();
            $table->foreignId('team_leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('engineer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_category', 32)->nullable();
            $table->string('original_severity', 16)->nullable();
            $table->float('composite_score')->nullable();
            $table->string('corrected_category', 32)->nullable();
            $table->string('corrected_severity', 16)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('final_decision')->nullable();
            $table->timestamp('feedback_timestamp')->useCurrent();
            $table->timestamp('review_timestamp')->nullable();
            $table->boolean('used_for_training')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('used_for_training');
        });

        DB::statement('ALTER TABLE tbl_feedback ADD COLUMN action_taken feedback_action NOT NULL');
        DB::statement('CREATE INDEX tbl_feedback_action_taken_idx ON tbl_feedback(action_taken)');
        DB::statement('ALTER TABLE tbl_feedback ADD CONSTRAINT fk_feedback_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE tbl_feedback ADD CONSTRAINT fk_feedback_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_feedback');
        DB::statement('DROP TYPE IF EXISTS feedback_action');
    }
};
