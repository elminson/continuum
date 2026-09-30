<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Results of running an AI component against its labelled benchmark,
     * kept so the site can show how well the model did, not just that it runs.
     */
    public function up(): void
    {
        Schema::create('ai_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('suite', 64);
            $table->string('provider', 32);
            $table->string('model', 64);
            $table->string('version', 64);
            $table->unsignedSmallInteger('cases');
            $table->json('scores');
            $table->json('results');
            $table->timestamp('ran_at');
            $table->timestamps();

            $table->index(['suite', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_evaluations');
    }
};
