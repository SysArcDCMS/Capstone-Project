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
     * tbl_incidents — capstone Section 3.4 (ER Diagram).
     *
     * Columns:
     *   incident_id (PK)             -> id
     *   customer_id (FK -> users)    -> customer_id
     *   description (text)           -> description
     *   location (text, nullable)    -> location
     *   category (string, nullable)  -> category  (set by NLP service)
     *   severity (string, nullable)  -> severity  (High | Medium | Low)
     *   status (enum)                -> status    (open|in_progress|resolved|rejected)
     *   submitted_at (timestamp)     -> submitted_at
     *   resolved_at (timestamp, null)-> resolved_at
     *   created_by, updated_by       -> audit columns
     *   composite_score (float, null)-> composite_score (capstone Process 2.9)
     *   created_at, updated_at       -> timestamps
     */
    public function up(): void
    {
        DB::statement("CREATE TYPE incident_status AS ENUM ('open', 'in_progress', 'resolved', 'rejected')");

        Schema::create('tbl_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->text('description');
            $table->text('location')->nullable();
            $table->string('category', 32)->nullable();
            $table->string('severity', 16)->nullable();
            $table->float('composite_score')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('severity');
            $table->index('submitted_at');
            $table->index('resolved_at');
        });

        DB::statement('ALTER TABLE tbl_incidents ADD COLUMN status incident_status NOT NULL DEFAULT \'open\'');
        DB::statement('ALTER TABLE tbl_incidents ADD CONSTRAINT fk_incidents_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE tbl_incidents ADD CONSTRAINT fk_incidents_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('CREATE INDEX tbl_incidents_status_idx ON tbl_incidents(status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_incidents');
        DB::statement('DROP TYPE IF EXISTS incident_status');
    }
};
