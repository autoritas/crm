<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->nullable()->after('id');
            $table->unique(['id_company', 'legacy_id'], 'offers_company_legacy_unique');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropUnique('offers_company_legacy_unique');
            $table->dropColumn('legacy_id');
        });
    }
};
