<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geographic coordinates for incidents — capstone mobile map scope.
 *
 * The mobile app captures the customer's location with a draggable
 * Google Maps pin at complaint submission. Both columns are nullable so
 * web-created incidents (no coordinates) remain valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_incidents', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('location');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_incidents', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};