<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalogo de competidores normalizados (como clients)
        Schema::dropIfExists('competitors');
        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_company')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('cif', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('id_company');
        });

        // Aliases de competidores (como client_aliases)
        Schema::create('competitor_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_company')->constrained('companies')->cascadeOnDelete();
            $table->string('raw_name');
            $table->foreignId('id_competitor')->nullable()->constrained('competitors')->nullOnDelete();
            $table->timestamps();
            $table->unique(['id_company', 'raw_name']);
        });

        // Competidores por oferta
        Schema::create('offer_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_offer')->constrained('offers')->cascadeOnDelete();
            $table->string('competitor_nombre', 255);
            $table->foreignId('id_competitor')->nullable()->constrained('competitors')->nullOnDelete();
            $table->enum('admision', ['Admitido', 'Excluido', 'Pendiente'])->default('Pendiente');
            $table->enum('razon_exclusion', ['Administrativa', 'Tecnica', 'Economica', 'Desconocida'])->nullable();
            $table->timestamps();
            $table->index(['id_offer', 'id_competitor']);
        });

        // Valoraciones cuantitativas por competidor en oferta
        Schema::create('offer_competitor_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_offer_competitor')->constrained('offer_competitors')->cascadeOnDelete();
            $table->decimal('tecnico', 5, 2)->nullable();
            $table->decimal('economico', 5, 2)->nullable();
            $table->decimal('objetivo_real', 5, 2)->nullable();
            $table->decimal('objetivo_fake', 5, 2)->nullable();
            $table->decimal('precio', 15, 2)->nullable();
            $table->timestamps();
            $table->unique('id_offer_competitor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_competitor_scores');
        Schema::dropIfExists('offer_competitors');
        Schema::dropIfExists('competitor_aliases');
        Schema::dropIfExists('competitors');
    }
};
