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
        Schema::table('infonalia_statuses', function (Blueprint $table) {
            $table->boolean('is_default_filter')->default(false)->after('generates_offer');
        });
    }

    public function down(): void
    {
        Schema::table('infonalia_statuses', function (Blueprint $table) {
            $table->dropColumn('is_default_filter');
        });
    }
};
