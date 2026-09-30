<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Readings now come from a language model with structured output: a
     * baseline check and a one-sentence rationale, no per-option probabilities.
     * Earlier decision-model readings failed evaluation and are dropped.
     */
    public function up(): void
    {
        DB::table('alert_triages')->delete();

        Schema::table('alert_triages', function (Blueprint $table) {
            $table->boolean('baseline_reliable')->nullable()->after('input_hash');
            $table->text('rationale')->nullable()->after('concern_level');
            $table->json('cause_probabilities')->nullable()->change();
            $table->float('concern_score')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('alert_triages', function (Blueprint $table) {
            $table->dropColumn(['baseline_reliable', 'rationale']);
        });
    }
};
