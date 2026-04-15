<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renombra la tabla local `companies` (BD del CRM) a `company_settings`.
 *
 * Motivo arquitectonico (ver CLAUDE.md + decision 2026-04-15):
 * La identidad de empresa (id + name) vive en Core (`autoritas_production`).
 * El CRM solo gestiona los ajustes propios (branding, Kanboard, modelo Go/NoGo).
 *
 * Cambios:
 *  - Drop de TODAS las FKs locales que apuntan a `companies`/`company_settings`,
 *    porque `company_id` en las tablas CRM referencia logicamente a
 *    `core.companies.id` (otra BD/host). No hay FK fisica posible.
 *  - RENAME TABLE companies -> company_settings (si aun no se ha hecho).
 *  - Drop columnas duplicadas con core: `name`, `is_active`.
 *  - Rename columna `id` -> `company_id` (PK logica hacia core.companies.id).
 *
 * La migracion es idempotente: puede re-ejecutarse sin efecto si ya esta aplicada.
 * Ejecuta solo en la conexion local (default `mysql`).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Si aun existe `companies`, renombrar.
        if (Schema::hasTable('companies') && !Schema::hasTable('company_settings')) {
            Schema::rename('companies', 'company_settings');
        }

        if (!Schema::hasTable('company_settings')) {
            return;
        }

        // 2) Drop columnas duplicadas con core (si siguen presentes).
        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('company_settings', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        // 3) Drop de TODAS las FKs que apuntan a company_settings. Son FKs
        //    de la epoca en que companies era la tabla maestra local.
        //    Ahora company_id referencia logicamente a core.companies.id,
        //    que esta en otro host — no puede haber FK fisica.
        $fks = DB::select("
            SELECT TABLE_NAME, CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME = 'company_settings'
        ");
        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }

        // 4) Rename PK: id -> company_id (si aun no se ha hecho).
        if (Schema::hasColumn('company_settings', 'id') && !Schema::hasColumn('company_settings', 'company_id')) {
            DB::statement('ALTER TABLE company_settings CHANGE `id` `company_id` BIGINT UNSIGNED NOT NULL');
        }
    }

    public function down(): void
    {
        // No-op: revertir drop de FKs masivo es inviable.
        // Si necesitas volver atras, recrea la tabla manualmente.
    }
};
