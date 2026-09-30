<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The hotspot map asks for one day, one sensor and a box: with the
     * coordinates in the index, Postgres rejects out-of-box cells without
     * touching the table (34k heap reads down to ~2k for a Gran Chaco day).
     */
    public function up(): void
    {
        Schema::table('cell_days', function (Blueprint $table) {
            $table->index(['acq_date', 'instrument', 'latitude', 'longitude'], 'cell_days_day_sensor_location_index');
        });
    }

    public function down(): void
    {
        Schema::table('cell_days', function (Blueprint $table) {
            $table->dropIndex('cell_days_day_sensor_location_index');
        });
    }
};
