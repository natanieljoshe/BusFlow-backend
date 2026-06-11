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
        Schema::create('ml_models', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->string('algorithm');
            $table->decimal('accuracy', 5, 4)->nullable();
            $table->decimal('mae', 10, 4)->nullable();
            $table->decimal('rmse', 10, 4)->nullable();
            $table->json('hyperparameters')->nullable();
            $table->string('model_file_path')->nullable();
            $table->unsignedInteger('training_rows')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('trained_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ml_models');
    }
};
