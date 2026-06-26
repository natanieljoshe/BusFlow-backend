<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->dropForeign(['conductor_id']);
            $table->dropColumn('driver_id');
            $table->dropColumn('conductor_id');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('bus_id')->nullable()->constrained('buses')->nullOnDelete();
        });

        Schema::table('conductors', function (Blueprint $table) {
            $table->foreignId('bus_id')->nullable()->constrained('buses')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropForeign(['bus_id']);
            $table->dropColumn('bus_id');
        });

        Schema::table('conductors', function (Blueprint $table) {
            $table->dropForeign(['bus_id']);
            $table->dropColumn('bus_id');
        });

        Schema::table('buses', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('conductor_id')->nullable()->constrained('conductors')->nullOnDelete();
        });
    }
};
