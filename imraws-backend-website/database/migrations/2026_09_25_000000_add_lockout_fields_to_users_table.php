<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account lockout — capstone security scope.
 *
 * Tracks consecutive failed login attempts per user. After 3 failures the
 * account is locked for 15 minutes (locked_until), enforced in both the
 * mobile API login (AuthController) and the web portal login
 * (PortalController). Both counters reset on a successful login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('failed_login_attempts')->default(0)->after('is_team_leader');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['failed_login_attempts', 'locked_until']);
        });
    }
};