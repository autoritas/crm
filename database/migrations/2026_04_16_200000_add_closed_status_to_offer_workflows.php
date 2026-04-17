<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anade a offer_workflows la FK opcional `closed_offer_status_id`.
 *
 * Significado: si una tarea Kanboard asociada a una oferta en esta fase
 * se cierra, la oferta pasara al `offer_status` indicado aqui.
 *
 * Ejemplo: PROSPECTS cerrada -> Descartado; EN DECISION cerrada -> Perdido;
 * GANADO cerrada -> Ganado (queda cerrada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offer_workflows', function (Blueprint $table) {
            if (! Schema::hasColumn('offer_workflows', 'closed_offer_status_id')) {
                $table->foreignId('closed_offer_status_id')
                    ->nullable()
                    ->after('kanboard_column_id')
                    ->constrained('offer_statuses')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('offer_workflows', function (Blueprint $table) {
            if (Schema::hasColumn('offer_workflows', 'closed_offer_status_id')) {
                $table->dropConstrainedForeignId('closed_offer_status_id');
            }
        });
    }
};
