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
        Schema::table('infonalia_statuses', function (Blueprint $table) {
            $table->string('color', 7)->default('#6b7280')->after('status');
            $table->boolean('generates_offer')->default(false)->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('infonalia_statuses', function (Blueprint $table) {
            $table->dropColumn(['color', 'generates_offer']);
        });
    }
};
