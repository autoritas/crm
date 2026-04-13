<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            // === Detalles (ex details) ===
            $table->foreignId('id_business_line')->nullable()->after('notas')
                ->constrained('offer_business_lines')->nullOnDelete();
            $table->foreignId('id_client_activity')->nullable()->after('id_business_line')
                ->constrained('offer_client_activities')->nullOnDelete();
            $table->foreignId('id_workflow')->nullable()->after('id_client_activity')
                ->constrained('offer_workflows')->nullOnDelete();
            $table->enum('renovable', ['Si', 'No', 'Desconocido'])->nullable()->after('id_workflow');
            $table->enum('fidelizacion', ['Nuevo', 'Cliente', 'Desconocido'])->nullable()->after('renovable');
            $table->string('kanboard_task', 255)->nullable()->after('fidelizacion');
            $table->unsignedBigInteger('responsable')->nullable()->after('kanboard_task');

            // === Fechas (ex dates) ===
            $table->date('fecha_anuncio')->nullable()->after('fecha_presentacion');
            $table->date('fecha_publicacion')->nullable()->after('fecha_anuncio');
            $table->date('fecha_adjudicacion')->nullable()->after('fecha_publicacion');
            $table->date('fecha_formalizacion')->nullable()->after('fecha_adjudicacion');
            $table->date('fecha_fin_contrato')->nullable()->after('fecha_formalizacion');

            // === Criterios pliego (ex criterios) ===
            $table->decimal('peso_economica', 5, 2)->nullable()->after('fecha_fin_contrato');
            $table->decimal('peso_tecnica', 5, 2)->nullable()->after('peso_economica');
            $table->decimal('peso_objetiva_real', 5, 2)->nullable()->after('peso_tecnica');
            $table->decimal('peso_objetiva_fake', 5, 2)->nullable()->after('peso_objetiva_real');
            $table->foreignId('id_formula')->nullable()->after('peso_objetiva_fake')
                ->constrained('offer_formulas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['id_business_line']);
            $table->dropForeign(['id_client_activity']);
            $table->dropForeign(['id_workflow']);
            $table->dropForeign(['id_formula']);
            $table->dropColumn([
                'id_business_line', 'id_client_activity', 'id_workflow',
                'renovable', 'fidelizacion', 'kanboard_task', 'responsable',
                'fecha_anuncio', 'fecha_publicacion', 'fecha_adjudicacion',
                'fecha_formalizacion', 'fecha_fin_contrato',
                'peso_economica', 'peso_tecnica', 'peso_objetiva_real',
                'peso_objetiva_fake', 'id_formula',
            ]);
        });
    }
};
