<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valuations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_company')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('id_offer')->nullable()->constrained('offers')->nullOnDelete();
            $table->foreignId('id_competitor')->nullable()->constrained('competitors')->nullOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('criteria')->nullable();
            $table->text('qualitative_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('id_company');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valuations');
    }
};
