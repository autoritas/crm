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
        Schema::table('infonalia_data', function (Blueprint $table) {
            $table->foreignId('id_screening_reason')->nullable()->after('revisado_fecha')
                ->constrained('screening_reasons')->nullOnDelete();
            $table->text('screening_comment')->nullable()->after('id_screening_reason');
        });
    }

    public function down(): void
    {
        Schema::table('infonalia_data', function (Blueprint $table) {
            $table->dropForeign(['id_screening_reason']);
            $table->dropColumn(['id_screening_reason', 'screening_comment']);
        });
    }
};
