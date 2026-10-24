<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SEPA-betaalbestand: onthouden welke inkoopfacturen in welk bestand zijn meegegaan, zodat niets dubbel wordt betaald.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->timestamp('sepa_exported_at')->nullable();
            $table->string('sepa_batch', 35)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropIndex(['sepa_batch']);
            $table->dropColumn(['sepa_exported_at', 'sepa_batch']);
        });
    }
};
