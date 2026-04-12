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
        Schema::create('client_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_company')->constrained('companies')->cascadeOnDelete();
            $table->string('raw_name');
            $table->foreignId('id_client')->nullable()->constrained('clients')->nullOnDelete();
            $table->timestamps();

            $table->unique(['id_company', 'raw_name']);
            $table->index('id_client');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_aliases');
    }
};
