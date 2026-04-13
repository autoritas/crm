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
        // Eliminar tabla offers anterior (placeholder) y sus dependencias
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('valuations');
        Schema::dropIfExists('offers');
        Schema::enableForeignKeyConstraints();

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_company')->constrained('companies')->cascadeOnDelete();

            // Origen: de donde viene la oferta
            $table->foreignId('id_infonalia_data')->nullable()->constrained('infonalia_data')->nullOnDelete();

            // Cliente
            $table->string('cliente', 512)->nullable();
            $table->foreignId('id_client')->nullable()->constrained('clients')->nullOnDelete();

            // Datos del proyecto
            $table->string('codigo_proyecto', 20)->nullable();
            $table->string('proyecto', 4096)->nullable();
            $table->text('objeto')->nullable();

            // Clasificación
            $table->enum('sector', ['Público', 'Privado'])->nullable();
            $table->foreignId('id_offer_type')->nullable()->constrained('offer_types')->nullOnDelete();
            $table->foreignId('id_offer_status')->nullable()->constrained('offer_statuses')->nullOnDelete();
            $table->string('temperatura', 20)->nullable();

            // Importes y plazos
            $table->date('fecha_presentacion')->nullable();
            $table->decimal('importe_licitacion', 15, 2)->nullable();
            $table->decimal('importe_estimado', 15, 2)->nullable();
            $table->unsignedInteger('duracion_meses')->nullable();

            // Extra
            $table->string('provincia', 100)->nullable();
            $table->string('url', 500)->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index('id_company');
            $table->index(['id_company', 'id_offer_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
