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
        Schema::create('screening_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index();
            $table->enum('type', ['positive', 'negative']);
            $table->string('reason');
            $table->timestamps();

            $table->index(['company_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screening_reasons');
    }
};
