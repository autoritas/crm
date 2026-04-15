<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index();
            $table->foreignId('id_opportunity')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('status')->default('draft');
            $table->date('submitted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
