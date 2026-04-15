<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infonalia_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index();

            // Decision - referencia a infonalia_statuses
            $table->foreignId('id_decision')->nullable()->constrained('infonalia_statuses')->nullOnDelete();

            // Campos comunes
            $table->date('fecha_publicacion')->nullable();
            $table->string('cliente', 255)->nullable();
            $table->text('resumen_objeto')->nullable();
            $table->string('provincia', 100)->nullable();
            $table->decimal('presupuesto', 15, 2)->nullable();
            $table->date('presentacion')->nullable();
            $table->string('perfil_contratante', 255)->nullable();
            $table->timestamp('fecha_ingreso')->nullable();
            $table->string('url', 500)->nullable();
            $table->integer('kanboard_task_id')->nullable();

            // Campos IA (Autoritas)
            $table->foreignId('id_ia_decision')->nullable()->constrained('infonalia_statuses')->nullOnDelete();
            $table->text('ia_motivo')->nullable();
            $table->timestamp('ia_fecha')->nullable();
            $table->boolean('revisado_humano')->default(false);
            $table->timestamp('revisado_fecha')->nullable();

            $table->timestamps();

            $table->index('company_id');
            $table->index(['company_id', 'id_decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infonalia_data');
    }
};
