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
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('kanboard_project_id')->nullable()->after('primary_color');
        });

        // Tabla de mapeo de columnas Kanboard por empresa
        Schema::create('company_kanboard_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_company')->constrained('companies')->cascadeOnDelete();
            $table->unsignedInteger('kanboard_column_id');
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['id_company', 'kanboard_column_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_kanboard_columns');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('kanboard_project_id');
        });
    }
};
