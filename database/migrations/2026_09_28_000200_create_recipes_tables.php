<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Weight of the finished dish; per-100 g values are derived from this, not the raw ingredient sum.
            $table->decimal('total_weight_g', 8, 2);
            $table->decimal('default_portion_g', 7, 2)->nullable();
            $table->boolean('is_dish')->default(false);
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_id')->constrained('foods')->restrictOnDelete();
            $table->decimal('grams', 8, 2);
            $table->timestamps();
        });

        Schema::create('food_portions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_id')->nullable()->constrained('foods')->cascadeOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('grams', 7, 2);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE food_portions ADD CONSTRAINT food_portions_one_owner CHECK ((food_id IS NULL) <> (recipe_id IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('food_portions');
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');
    }
};
