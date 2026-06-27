<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pais_panel', function (Blueprint $table) {
            $table->text('powerbi_url')->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('pais_panel', function (Blueprint $table) {
            $table->dropColumn('powerbi_url');
        });
    }
};
