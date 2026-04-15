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
        // Nota: `companies.go_nogo_model` vive en la BD de Stockflow Core
        // (ver migracion de core que anade campos CRM a companies).

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
    }
};
