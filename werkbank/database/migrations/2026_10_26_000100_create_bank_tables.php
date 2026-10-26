<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bankgegevens uit Ponto (alleen lezen): rekeningen en transacties, met een koppelvoorstel per transactie.
// Een betaling wordt pas geboekt als een gebruiker het voorstel bevestigt.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('ponto_id', 64)->unique();
            $table->string('iban', 40)->nullable();
            $table->string('description')->nullable();
            $table->string('holder_name')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('current_balance', 14, 2)->nullable();
            $table->decimal('available_balance', 14, 2)->nullable();
            $table->timestamp('authorization_expires_at')->nullable();
            $table->timestamp('synchronized_at')->nullable(); // laatste synchronisatie door Ponto met de bank
            $table->string('availability', 20)->nullable();   // available | readonly
            $table->timestamp('fetched_at')->nullable();      // laatste keer opgehaald door Werkbank
            $table->timestamps();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('ponto_id', 64)->unique();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('counterpart_name')->nullable();
            $table->string('counterpart_iban', 40)->nullable();
            $table->text('remittance')->nullable();
            $table->text('description')->nullable();
            $table->string('end_to_end_id', 35)->nullable();
            // nieuw (geen voorstel) | voorstel | gekoppeld | genegeerd
            $table->string('status', 20)->default('nieuw');
            $table->nullableMorphs('proposal');          // voorgestelde factuur (invoice of purchase_invoice)
            $table->string('proposal_reason')->nullable();
            $table->boolean('proposal_certain')->default(false);
            $table->nullableMorphs('payment');           // geboekte betaling (payment of purchase_payment)
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
    }
};
