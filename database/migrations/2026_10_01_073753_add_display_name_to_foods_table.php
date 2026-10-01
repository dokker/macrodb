<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The user's own name for a food. It is kept apart from `name`, which importers overwrite on every re-run.
     */
    public function up(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
