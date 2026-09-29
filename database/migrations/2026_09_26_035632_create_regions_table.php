<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('west', 9, 6);
            $table->decimal('south', 9, 6);
            $table->decimal('east', 9, 6);
            $table->decimal('north', 9, 6);
            $table->string('color', 9)->default('#f97316');
            $table->boolean('is_preset')->default(false);
            $table->timestamp('harmonized_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
