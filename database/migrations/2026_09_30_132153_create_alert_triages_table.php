<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Advisory reading of each flagged period by a decision model (Laya).
     * Keyed like an alert rather than by alert id, because alerts are rebuilt
     * on every harmonization: the reading survives and is only refreshed when
     * the numbers behind it change (input_hash).
     */
    public function up(): void
    {
        Schema::create('alert_triages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('period', 5);
            $table->date('period_start');
            $table->string('method', 16);
            $table->string('reference', 8);
            $table->char('input_hash', 40);
            $table->string('cause', 16);
            $table->json('cause_probabilities');
            $table->float('cause_confidence')->nullable();
            $table->float('artifact_probability');
            $table->float('concern_score');
            $table->unsignedTinyInteger('concern_level');
            $table->string('provider', 32);
            $table->string('model', 64);
            $table->timestamp('triaged_at');
            $table->timestamps();

            $table->unique(['region_id', 'period', 'period_start', 'method', 'reference'], 'alert_triages_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_triages');
    }
};
