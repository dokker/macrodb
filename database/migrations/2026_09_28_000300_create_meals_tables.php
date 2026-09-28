<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->dateTime('eaten_at'); // UTC
            $table->string('meal_type', 16);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('eaten_at');
        });

        Schema::create('meal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_id')->nullable()->constrained('foods')->restrictOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('grams', 8, 2);
            $table->string('source_text')->nullable();
            $table->string('input_method', 16);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE meal_items ADD CONSTRAINT meal_items_one_item CHECK ((food_id IS NULL) <> (recipe_id IS NULL))');

        Schema::create('daily_targets', function (Blueprint $table) {
            $table->id();
            $table->date('valid_from')->unique();
            $table->unsignedSmallInteger('kcal');
            $table->decimal('protein', 6, 2);
            $table->decimal('carbs', 6, 2);
            $table->decimal('fat', 6, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_targets');
        Schema::dropIfExists('meal_items');
        Schema::dropIfExists('meals');
    }
};
