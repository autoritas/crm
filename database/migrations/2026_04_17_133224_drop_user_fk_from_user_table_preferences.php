<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los usuarios viven en la conexion `autoritas_production` (tabla
 * `stockflow_users`), no en la `users` local. La FK cross-DB no puede
 * validarse y rompe cualquier insert en `user_table_preferences`.
 *
 * Soltamos la FK y mantenemos solo el indice para consultas rapidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fkExists = collect(DB::select(
            "SELECT CONSTRAINT_NAME
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'user_table_preferences'
                AND CONSTRAINT_NAME = 'user_table_preferences_user_id_foreign'"
        ))->isNotEmpty();

        if ($fkExists) {
            Schema::table('user_table_preferences', function (Blueprint $table) {
                $table->dropForeign('user_table_preferences_user_id_foreign');
            });
        }

        $indexExists = collect(DB::select(
            "SHOW INDEX FROM user_table_preferences WHERE Key_name = 'user_table_preferences_user_id_index'"
        ))->isNotEmpty();

        if (! $indexExists) {
            Schema::table('user_table_preferences', function (Blueprint $table) {
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        // No se restaura la FK: apuntaria a una tabla de una conexion distinta.
    }
};
