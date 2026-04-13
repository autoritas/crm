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
        // Modelo Go/NoGo por empresa (texto grande con los criterios para la IA)
        Schema::table('companies', function (Blueprint $table) {
            $table->longText('go_nogo_model')->nullable()->after('kanboard_project_id');
        });

        // Campos Go/NoGo en ofertas
        Schema::table('offers', function (Blueprint $table) {
            $table->enum('go_nogo', ['GO', 'GO_TACTICO', 'NO_GO', 'PENDIENTE'])->default('PENDIENTE')->after('notas');
            $table->enum('ia_go_nogo', ['GO', 'GO_TACTICO', 'NO_GO'])->nullable()->after('go_nogo');
            $table->text('ia_go_nogo_analysis')->nullable()->after('ia_go_nogo');
            $table->timestamp('ia_go_nogo_date')->nullable()->after('ia_go_nogo_analysis');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['go_nogo', 'ia_go_nogo', 'ia_go_nogo_analysis', 'ia_go_nogo_date']);
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('go_nogo_model');
        });
    }
};
