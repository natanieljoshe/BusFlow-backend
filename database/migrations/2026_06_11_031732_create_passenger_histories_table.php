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
        Schema::create('passenger_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
            $table->foreignId('csv_upload_id')->constrained('csv_uploads')->onDelete('cascade');
            $table->date('record_date');
            $table->tinyInteger('hour')->unsigned();
            $table->enum('day_type', ['weekday', 'weekend', 'holiday']);
            $table->unsignedInteger('passenger_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passenger_histories');
    }
};
