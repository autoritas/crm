<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->date('deadline')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
