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
        Schema::create('api_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_company')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('service');
            $table->string('label')->nullable();
            $table->string('base_url')->nullable();
            $table->text('api_key')->nullable();
            $table->string('folder')->nullable();
            $table->json('extra')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['id_company', 'service']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_credentials');
    }
};
