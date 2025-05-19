<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configured_prompts', function (Blueprint $table) {
            //
            $table->float('estimated_confidence_score', 3, 2)
                ->nullable()->default(0.0)->after('version');
            // Atau jika Anda lebih suka DECIMAL:
            // $table->decimal('estimated_confidence_score', 3, 2)->nullable()->default(0.00)->after('version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configured_prompts', function (Blueprint $table) {
            //
            $table->dropColumn('estimated_confidence_score');
        });
    }
};
