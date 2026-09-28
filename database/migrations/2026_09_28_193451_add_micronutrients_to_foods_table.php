<?php

use App\Support\Nutrients;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Micronutrients per 100 g, all nullable: public sources often lack them.
     */
    public function up(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            foreach (Nutrients::MICROS as $column) {
                $table->decimal($column, 9, 3)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->dropColumn(Nutrients::MICROS);
        });
    }
};
