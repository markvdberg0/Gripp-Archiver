<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Relaties samenvoegen: de opgeheven relatie wijst naar de relatie die blijft. Bewust geen foreign key:
// een verwijzing binnen dezelfde tabel kan bij herstel op PostgreSQL niet in willekeurige volgorde worden ingevoegd.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('merged_into_id')->nullable()->index();
            $table->timestamp('merged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['merged_into_id']);
            $table->dropColumn(['merged_into_id', 'merged_at']);
        });
    }
};
