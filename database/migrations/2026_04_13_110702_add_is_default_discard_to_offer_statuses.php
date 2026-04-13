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
        Schema::table('offer_statuses', function (Blueprint $table) {
            $table->boolean('is_default_discard')->default(false)->after('is_default_filter');
        });
    }

    public function down(): void
    {
        Schema::table('offer_statuses', function (Blueprint $table) {
            $table->dropColumn('is_default_discard');
        });
    }
};
