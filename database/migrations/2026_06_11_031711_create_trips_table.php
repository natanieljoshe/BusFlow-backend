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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->onDelete('cascade');
            $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
            $table->foreignId('bus_id')->nullable()->constrained('buses')->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->onDelete('cascade');
            $table->foreignId('conductor_id')->nullable()->constrained('conductors')->onDelete('cascade');
            $table->time('departure_time');
            $table->time('estimated_arrival')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
