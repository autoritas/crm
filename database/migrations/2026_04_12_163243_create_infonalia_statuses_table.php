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
        Schema::create('infonalia_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_company')->constrained('companies')->cascadeOnDelete();
            $table->string('status');
            $table->timestamps();

            $table->unique(['id_company', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infonalia_statuses');
    }
};
