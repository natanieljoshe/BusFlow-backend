<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->time('shift_start')->nullable()->after('is_available');
            $table->time('shift_end')->nullable()->after('shift_start');
        });

        Schema::table('conductors', function (Blueprint $table) {
            $table->time('shift_start')->nullable()->after('is_available');
            $table->time('shift_end')->nullable()->after('shift_start');
        });
    }

    public function down(): void
    {
        Schema::table('staff_tables', function (Blueprint $table) {
            //
        });
    }
};
