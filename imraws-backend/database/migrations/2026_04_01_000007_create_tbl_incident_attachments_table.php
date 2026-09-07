<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * tbl_incident_attachments — capstone DFD Level 2 Process 5.6
     * (Resolution Tracking): "Attach Photo Proof – Optional".
     *
     * Stored separately from tbl_assignments so that proof images are
     * independently queryable and deletable. Each row references the
     * incident it documents. The actual file lives on the Laravel public
     * disk at storage/app/public/attachments/.
     *
     * Columns:
     *   attachment_id (PK) -> id
     *   incident_id (FK)   -> incident_id
     *   uploaded_by (FK -> users) -> uploaded_by
     *   file_path (string) -> file_path
     *   original_name     -> original_name
     *   mime_type         -> mime_type
     *   file_size (int)   -> file_size
     *   caption (text, null) -> caption
     *   created_at, updated_at -> timestamps
     */
    public function up(): void
    {
        Schema::create('tbl_incident_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('tbl_incidents')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('file_path', 512);
            $table->string('original_name', 255);
            $table->string('mime_type', 64);
            $table->unsignedInteger('file_size');
            $table->text('caption')->nullable();
            $table->timestamps();

            $table->index('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_incident_attachments');
    }
};
