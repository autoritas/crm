<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca que fase del workflow comercial es la que dispara la lectura del
 * Go/No Go y el envio al flujo de n8n. Cada empresa decide su propia fase
 * (habitualmente PROSPECTS u OFERTAR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offer_workflows', function (Blueprint $table) {
            if (! Schema::hasColumn('offer_workflows', 'is_go_nogo_phase')) {
                $table->boolean('is_go_nogo_phase')
                    ->default(false)
                    ->after('closed_offer_status_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offer_workflows', function (Blueprint $table) {
            if (Schema::hasColumn('offer_workflows', 'is_go_nogo_phase')) {
                $table->dropColumn('is_go_nogo_phase');
            }
        });
    }
};
