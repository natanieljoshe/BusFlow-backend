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
        Schema::create('ga_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->onDelete('cascade');
            $table->unsignedInteger('population_size')->default(50);
            $table->unsignedInteger('generations')->default(100);
            $table->decimal('crossover_rate', 4, 2)->default(0.8);
            $table->decimal('mutation_rate', 4, 2)->default(0.1);
            $table->string('selection_method')->default('tournament');
            $table->unsignedSmallInteger('elitism_count')->default(2);
            $table->unsignedSmallInteger('min_driver_rest_min')->default(30);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ga_parameters');
    }
};
