<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anade `is_default_discard` a `infonalia_statuses`. Con el nuevo modelo
 * de cribado (pulgar arriba = Ofertar, pulgar abajo = Descartar) necesitamos
 * saber explicitamente cual es el estado "descartar" por empresa.
 *
 * Seeding automatico: marca como true el primer status llamado 'Descartar'
 * por empresa. El admin puede cambiarlo despues si procede.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infonalia_statuses', function (Blueprint $table) {
            if (! Schema::hasColumn('infonalia_statuses', 'is_default_discard')) {
                $table->boolean('is_default_discard')
                    ->default(false)
                    ->after('is_default_filter');
            }
        });

        // Marca Descartar por defecto en cada empresa.
        DB::table('infonalia_statuses')
            ->whereRaw('LOWER(status) = ?', ['descartar'])
            ->update(['is_default_discard' => true]);
    }

    public function down(): void
    {
        Schema::table('infonalia_statuses', function (Blueprint $table) {
            if (Schema::hasColumn('infonalia_statuses', 'is_default_discard')) {
                $table->dropColumn('is_default_discard');
            }
        });
    }
};
