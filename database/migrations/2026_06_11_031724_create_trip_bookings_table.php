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
        Schema::create('trip_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->foreignId('boarding_stop_id')->constrained('haltes')->onDelete('cascade');
            $table->foreignId('arrive_stop_id')->constrained('haltes')->onDelete('cascade');
            $table->string('qr_code_token')->unique();
            $table->enum('status', ['booked', 'active', 'completed'])->default('booked');
            $table->decimal('fare', 10, 2)->default(0);
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('tapped_in_at')->nullable();
            $table->timestamp('tapped_out_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_bookings');
    }
};
