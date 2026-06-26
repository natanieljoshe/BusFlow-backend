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
            $table->foreignId('driver_id')
                  ->nullable()
                  ->after('total_distance')
                  ->constrained('drivers')
                  ->nullOnDelete();

            $table->foreignId('route_id')
                  ->nullable()
                  ->after('driver_id')
                  ->constrained('routes')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->dropForeign(['route_id']);
            $table->dropColumn(['driver_id', 'route_id']);
        });
    }
};
