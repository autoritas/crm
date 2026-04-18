<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anade `offer_resources` (LONGTEXT) a `offers` para guardar el razonamiento
 * completo generado por la IA (Go/No Go, analisis, etc). Se coloca al lado
 * del resto de campos IA (`ia_go_nogo_analysis`, `ia_go_nogo_date`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (! Schema::hasColumn('offers', 'offer_resources')) {
                $table->longText('offer_resources')
                    ->nullable()
                    ->after('ia_go_nogo_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'offer_resources')) {
                $table->dropColumn('offer_resources');
            }
        });
    }
};
