<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detections', function (Blueprint $table) {
            $table->id();
            $table->char('fingerprint', 32)->unique();
            $table->foreignId('ingest_run_id')->constrained()->cascadeOnDelete();
            $table->string('instrument', 8);
            $table->string('satellite', 4);
            $table->timestamp('acquired_at');
            $table->date('acq_date');
            $table->decimal('latitude', 8, 5);
            $table->decimal('longitude', 9, 5);
            $table->decimal('brightness', 6, 2);
            $table->decimal('brightness_secondary', 6, 2)->nullable();
            $table->decimal('scan', 5, 2);
            $table->decimal('track', 5, 2);
            $table->decimal('footprint_km2', 8, 4);
            $table->decimal('frp', 9, 2);
            $table->string('confidence_raw', 8);
            $table->string('confidence', 8);
            $table->char('daynight', 1);
            $table->string('version', 12);
            $table->unsignedBigInteger('cell_id');
            $table->unsignedInteger('calendar_cell_id');

            $table->index(['acq_date', 'instrument']);
            $table->index(['cell_id', 'acq_date']);
            $table->index(['latitude', 'longitude']);
            $table->index(['calendar_cell_id', 'acq_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detections');
    }
};
