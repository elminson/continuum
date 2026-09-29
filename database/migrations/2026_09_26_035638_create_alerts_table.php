<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('period', 5);
            $table->date('period_start');
            $table->string('metric', 16);
            $table->string('method', 16);
            $table->string('reference', 8);
            $table->decimal('observed', 12, 2);
            $table->decimal('expected', 12, 2);
            $table->decimal('spread', 12, 2);
            $table->decimal('z_score', 8, 3);
            $table->decimal('percentile', 5, 1);
            $table->string('severity', 10);
            $table->unsignedSmallInteger('climatology_years');
            $table->string('message');
            $table->timestamps();

            $table->unique(['region_id', 'period', 'period_start', 'metric', 'method', 'reference'], 'alerts_unique');
            $table->index(['severity', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
