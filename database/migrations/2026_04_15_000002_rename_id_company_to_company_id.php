<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renombra la columna `id_company` -> `company_id` en todas las tablas de
 * negocio locales (solo si existe con el nombre antiguo).
 */
return new class extends Migration
{
    /**
     * Tablas del CRM que llevaban `id_company` y pasan a `company_id`.
     */
    private array $tables = [
        'opportunities',
        'offers',
        'competitors',
        'competitor_aliases',
        'valuations',
        'infonalia_data',
        'infonalia_statuses',
        'clients',
        'client_aliases',
        'offer_statuses',
        'offer_types',
        'offer_business_lines',
        'offer_client_activities',
        'offer_formulas',
        'offer_workflows',
        'offer_competitors',
        'offer_competitor_scores',
        'screening_reasons',
        'api_credentials',
        'company_kanboard_columns',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'id_company') && !Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->renameColumn('id_company', 'company_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'company_id') && !Schema::hasColumn($table, 'id_company')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->renameColumn('company_id', 'id_company');
                });
            }
        }
    }
};
