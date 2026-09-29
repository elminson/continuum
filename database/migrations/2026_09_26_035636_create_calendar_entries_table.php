<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('period', 5);
            $table->date('period_start');
            $table->string('instrument', 8);
            $table->unsignedInteger('detections')->default(0);
            $table->unsignedInteger('active_cells')->default(0);
            $table->unsignedInteger('cell_days')->default(0);
            $table->decimal('frp_sum', 12, 2)->default(0);
            $table->decimal('frp_max', 9, 2)->default(0);
            $table->decimal('footprint_km2', 12, 3)->default(0);
            $table->unsignedInteger('day_detections')->default(0);
            $table->unsignedInteger('night_detections')->default(0);
            $table->unsignedInteger('low_confidence')->default(0);
            $table->unsignedInteger('nominal_confidence')->default(0);
            $table->unsignedInteger('high_confidence')->default(0);
            $table->unsignedSmallInteger('active_days')->default(0);
            $table->unsignedSmallInteger('coverage_days')->default(0);
            $table->json('frp_histogram')->nullable();
            $table->timestamps();

            $table->unique(['region_id', 'period', 'period_start', 'instrument'], 'calendar_entries_unique');
            $table->index(['region_id', 'period', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_entries');
    }
};
