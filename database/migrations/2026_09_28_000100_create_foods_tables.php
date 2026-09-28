<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nutrients are always per 100 g (per 100 ml for liquids).
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            $table->string('source', 16);
            $table->string('external_id')->nullable();
            $table->string('barcode', 32)->nullable()->unique();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->decimal('kcal', 7, 2);
            $table->decimal('protein', 6, 2);
            $table->decimal('carbs', 6, 2);
            $table->decimal('fat', 6, 2);
            $table->decimal('fiber', 6, 2)->nullable();
            $table->decimal('sugar', 6, 2)->nullable();
            $table->decimal('serving_size_g', 7, 2)->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index('name');
        });

        Schema::create('food_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_id')->constrained('foods')->cascadeOnDelete();
            $table->string('name');
            $table->string('lang', 8)->default('hu');
            $table->timestamps();

            $table->unique(['food_id', 'name', 'lang']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_aliases');
        Schema::dropIfExists('foods');
    }
};
