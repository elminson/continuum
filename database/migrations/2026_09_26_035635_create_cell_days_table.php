<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cell_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cell_id');
            $table->date('acq_date');
            $table->string('satellite', 4);
            $table->string('instrument', 8);
            $table->unsignedInteger('calendar_cell_id');
            $table->decimal('latitude', 8, 5);
            $table->decimal('longitude', 9, 5);
            $table->unsignedInteger('detections');
            $table->unsignedInteger('day_detections')->default(0);
            $table->unsignedInteger('night_detections')->default(0);
            $table->unsignedInteger('low_confidence')->default(0);
            $table->unsignedInteger('nominal_confidence')->default(0);
            $table->unsignedInteger('high_confidence')->default(0);
            $table->decimal('frp_sum', 10, 2);
            $table->decimal('frp_max', 9, 2);
            $table->decimal('footprint_km2', 9, 4);

            $table->unique(['cell_id', 'acq_date', 'satellite']);
            $table->index(['acq_date', 'instrument']);
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cell_days');
    }
};
