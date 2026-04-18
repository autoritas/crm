<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de pliegos adjuntados a la tarea Kanboard de una oferta.
 * Sirve para:
 *  - Idempotencia: misma (offer_id, sha256) no se re-adjunta.
 *  - Auditoria: que pliego vino de donde (source_url) y cuando.
 *  - Relacion con el file_id que Kanboard devuelve (el propio Kanboard se
 *    encarga de subirlos a S3 en su backend).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id')->index();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('provider', 40);           // 'PLACSP', 'PSCP', ...
            $table->text('source_url');
            $table->string('filename');
            $table->string('mime', 100)->nullable();
            $table->char('sha256', 64);
            $table->unsignedBigInteger('bytes')->nullable();
            $table->unsignedBigInteger('kanboard_task_id')->nullable();
            $table->unsignedBigInteger('kanboard_file_id')->nullable();
            $table->string('status', 20)->default('attached'); // 'attached' | 'failed'
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['offer_id', 'sha256'], 'offer_documents_dedup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_documents');
    }
};
