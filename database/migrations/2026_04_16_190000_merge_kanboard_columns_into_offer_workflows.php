<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unifica la tabla company_kanboard_columns dentro de offer_workflows.
 *
 * Motivacion: las fases del workflow comercial se corresponden 1:1 con
 * columnas de Kanboard. Tener dos tablas paralelas obligaba a mantener
 * manualmente la coherencia entre ambas. Ahora cada `offer_workflow`
 * guarda su `kanboard_column_id` directamente.
 *
 * Paso del dato:
 *  - Para cada company, emparejamos offer_workflows.sort_order con
 *    company_kanboard_columns.position (salvo cid=1 donde GANADO vive
 *    en position=6 y lo mapeamos al ultimo sort_order de la empresa).
 *  - Renombramos offer_workflows.name usando el nombre canonico Kanboard
 *    (PROSPECTS, OFERTAR, ...), que es el que usa el codigo existente
 *    via firstWhere('name', 'PROSPECTS').
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Anade columnas nuevas a offer_workflows
        Schema::table('offer_workflows', function (Blueprint $table) {
            if (! Schema::hasColumn('offer_workflows', 'kanboard_column_id')) {
                $table->unsignedInteger('kanboard_column_id')->nullable()->after('sort_order');
            }
            if (! Schema::hasColumn('offer_workflows', 'description')) {
                $table->text('description')->nullable()->after('kanboard_column_id');
            }
        });

        // 2. Traslada datos si la tabla vieja existe
        if (Schema::hasTable('company_kanboard_columns')) {
            $companies = DB::table('offer_workflows')->distinct()->pluck('company_id');

            foreach ($companies as $companyId) {
                $kbCols = DB::table('company_kanboard_columns')
                    ->where('company_id', $companyId)
                    ->orderBy('position')
                    ->get();

                $workflows = DB::table('offer_workflows')
                    ->where('company_id', $companyId)
                    ->orderBy('sort_order')
                    ->get();

                // Emparejamos por orden natural: n-esimo workflow ↔ n-esima columna.
                foreach ($workflows->values() as $i => $wf) {
                    $kb = $kbCols->get($i);
                    if (! $kb) continue;

                    DB::table('offer_workflows')
                        ->where('id', $wf->id)
                        ->update([
                            'name'               => $kb->name,
                            'kanboard_column_id' => $kb->kanboard_column_id,
                            'description'        => $kb->description,
                            'sort_order'         => $kb->position,
                            'updated_at'         => now(),
                        ]);
                }
            }

            Schema::dropIfExists('company_kanboard_columns');
        }
    }

    public function down(): void
    {
        // Restaurar la tabla con su estructura original.
        Schema::create('company_kanboard_columns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedInteger('kanboard_column_id');
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'kanboard_column_id']);
            $table->index(['company_id', 'position']);
        });

        // Volcar de nuevo los datos desde offer_workflows.
        $rows = DB::table('offer_workflows')
            ->whereNotNull('kanboard_column_id')
            ->get();

        foreach ($rows as $r) {
            DB::table('company_kanboard_columns')->insert([
                'company_id'         => $r->company_id,
                'kanboard_column_id' => $r->kanboard_column_id,
                'name'               => $r->name,
                'position'           => $r->sort_order,
                'description'        => $r->description,
                'created_at'         => $r->created_at,
                'updated_at'         => $r->updated_at,
            ]);
        }

        Schema::table('offer_workflows', function (Blueprint $table) {
            if (Schema::hasColumn('offer_workflows', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('offer_workflows', 'kanboard_column_id')) {
                $table->dropColumn('kanboard_column_id');
            }
        });
    }
};
