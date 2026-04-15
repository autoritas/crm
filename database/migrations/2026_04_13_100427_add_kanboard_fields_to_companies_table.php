<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * La tabla `companies` vive en la BD de Stockflow Core (conexion
     * autoritas_production). Los campos CRM de compania se anaden alli
     * en una migracion aparte. Aqui solo creamos el mapeo local de
     * columnas Kanboard por empresa.
     */
    public function up(): void
    {
        Schema::create('company_kanboard_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index();
            $table->unsignedInteger('kanboard_column_id');
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'kanboard_column_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_kanboard_columns');
    }
};
