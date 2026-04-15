<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lineas de negocio por empresa
        Schema::create('offer_business_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index();
            $table->string('name');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        // Actividades del cliente por empresa
        Schema::create('offer_client_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index();
            $table->string('name');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        // Workflows por empresa
        Schema::create('offer_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index();
            $table->string('name');
            $table->string('color', 7)->default('#6b7280');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        // Formulas de valoracion por empresa
        Schema::create('offer_formulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_formulas');
        Schema::dropIfExists('offer_workflows');
        Schema::dropIfExists('offer_client_activities');
        Schema::dropIfExists('offer_business_lines');
    }
};
