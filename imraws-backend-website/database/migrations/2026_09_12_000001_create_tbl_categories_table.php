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
     * tbl_categories — letter-compliant "Manage Complaint Categories"
     * screen registry (9th core table, added 2026-09-12).
     *
     * The initial 4 rows mirror the predefined category->department
     * mapping documented for DFD 3.2. `category_name` is the canonical
     * ai-nlp classifier label; `label` is the display title shown on
     * the web portal cards.
     */
    public function up(): void
    {
        Schema::create('tbl_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_name', 32)->unique();
            $table->string('label', 64)->nullable();
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });

        DB::statement('ALTER TABLE tbl_categories ADD CONSTRAINT fk_categories_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE tbl_categories ADD CONSTRAINT fk_categories_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_categories');
    }
};