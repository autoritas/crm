<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anade a la tabla `companies` de Stockflow Core los campos especificos
 * que el CRM necesita para personalizacion por empresa:
 *  - slug (identificador legible en URLs internas)
 *  - logo_path, icon_path, primary_color
 *  - kanboard_project_id, kanboard_default_category_id, kanboard_default_owner_id
 *  - go_nogo_model (plantilla de criterios Go/NoGo para la IA)
 *
 * Esta migracion se ejecuta contra la conexion `autoritas_production`
 * (BD core), no contra la BD local del CRM.
 */
return new class extends Migration
{
    protected $connection = 'autoritas_production';

    public function up(): void
    {
        Schema::connection($this->connection)->table('companies', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('companies', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('name');
            }
            if (!Schema::connection($this->connection)->hasColumn('companies', 'logo_path')) {
                $table->string('logo_path')->nullable();
            }
            if (!Schema::connection($this->connection)->hasColumn('companies', 'icon_path')) {
                $table->string('icon_path')->nullable();
            }
            if (!Schema::connection($this->connection)->hasColumn('companies', 'primary_color')) {
                $table->string('primary_color', 7)->nullable();
            }
            if (!Schema::connection($this->connection)->hasColumn('companies', 'kanboard_project_id')) {
                $table->unsignedInteger('kanboard_project_id')->nullable();
            }
            if (!Schema::connection($this->connection)->hasColumn('companies', 'kanboard_default_category_id')) {
                $table->unsignedInteger('kanboard_default_category_id')->nullable();
            }
            if (!Schema::connection($this->connection)->hasColumn('companies', 'kanboard_default_owner_id')) {
                $table->unsignedInteger('kanboard_default_owner_id')->nullable();
            }
            if (!Schema::connection($this->connection)->hasColumn('companies', 'go_nogo_model')) {
                $table->longText('go_nogo_model')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'logo_path',
                'icon_path',
                'primary_color',
                'kanboard_project_id',
                'kanboard_default_category_id',
                'kanboard_default_owner_id',
                'go_nogo_model',
            ]);
        });
    }
};
