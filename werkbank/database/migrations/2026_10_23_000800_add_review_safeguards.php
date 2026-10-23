<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Waarborgen na de codereview:
// - updated_by op inkoopfacturen, zodat het vier-ogenprincipe ook geldt voor wie de factuur het laatst wijzigde;
// - gripp_tombstones: welke geïmporteerde records lokaal definitief zijn verwijderd, zodat een volgende import ze niet terugzet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::create('gripp_tombstones', function (Blueprint $table) {
            $table->id();
            $table->string('model', 100);
            $table->unsignedBigInteger('gripp_id');
            $table->timestamp('deleted_at')->useCurrent();
            $table->unique(['model', 'gripp_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gripp_tombstones');
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
