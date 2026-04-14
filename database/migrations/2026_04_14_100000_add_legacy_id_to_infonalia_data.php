<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infonalia_data', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->nullable()->after('id');
            $table->unique(['id_company', 'legacy_id'], 'infonalia_data_company_legacy_unique');
        });
    }

    public function down(): void
    {
        Schema::table('infonalia_data', function (Blueprint $table) {
            $table->dropUnique('infonalia_data_company_legacy_unique');
            $table->dropColumn('legacy_id');
        });
    }
};
