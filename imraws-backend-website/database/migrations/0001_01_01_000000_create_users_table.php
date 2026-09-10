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
     * Extends Laravel's default users table to match the capstone
     * `tbl_users` schema (Section 3.4 — Project Design, ER Diagram).
     *
     * Capstone columns mapped:
     *   user_id           -> id (Laravel convention)
     *   full_name         -> full_name
     *   email             -> email (unique)
     *   password_hash     -> password (Laravel convention; bcrypt-hashed)
     *   contact_no        -> contact_no
     *   address           -> address
     *   role              -> role (enum: customer|administrator|engineer|offsite_staff)
     *   department_team   -> department_team
     *   is_active         -> is_active (boolean)
     *   is_team_leader    -> is_team_leader (boolean)
     *   created_at        -> created_at
     *   updated_at        -> updated_at
     */
    public function up(): void
    {
        // Use a Postgres ENUM for role to constrain values at the DB level.
        // This matches capstone Figure 4 (Process 1.6 Assign/Modify User Roles)
        // which gates behavior on role, and prevents bad data.
        DB::statement("CREATE TYPE user_role AS ENUM ('customer', 'administrator', 'engineer', 'offsite_staff')");

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('contact_no', 32)->nullable();
            $table->text('address')->nullable();
            $table->string('department_team', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_team_leader')->default(false);
            $table->rememberToken();
            $table->timestamps();

            $table->index('department_team');
            $table->index('is_team_leader');
        });

        // Add role as a typed column via raw SQL so the ENUM is used.
        // Laravel's ->enum() in Blueprint is deprecated on Postgres.
        DB::statement('ALTER TABLE users ADD COLUMN role user_role NOT NULL DEFAULT \'customer\'');
        DB::statement('CREATE INDEX users_role_idx ON users(role)');

        // Laravel framework tables (kept for auth/session support)
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        DB::statement('DROP TYPE IF EXISTS user_role');
    }
};
