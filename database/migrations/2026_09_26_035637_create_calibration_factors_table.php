<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_factors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('method', 16);
            $table->string('reference', 8);
            $table->unsignedTinyInteger('month')->default(0)->comment('1-12, or 0 for the all-year factor');
            $table->string('metric', 16);
            $table->decimal('value', 12, 4);
            $table->decimal('ci_low', 12, 4)->nullable();
            $table->decimal('ci_high', 12, 4)->nullable();
            $table->unsignedInteger('sample_days')->default(0);
            $table->date('overlap_from')->nullable();
            $table->date('overlap_to')->nullable();
            $table->json('validation')->nullable();
            $table->string('version', 12);
            $table->timestamp('calibrated_at');
            $table->timestamps();

            $table->unique(['region_id', 'method', 'reference', 'month', 'metric'], 'calibration_factors_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_factors');
    }
};
