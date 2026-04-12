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
            $table->foreignId('id_client')->nullable()->after('id_decision')->constrained('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('infonalia_data', function (Blueprint $table) {
            $table->dropForeign(['id_client']);
            $table->dropColumn('id_client');
        });
    }
};
