<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 4: verkoopfacturen met regels en betalingen, contracten (periodiek factureren).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->unsignedBigInteger('number')->unique();
            $table->string('name');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('actief'); // actief | beeindigd
            $table->string('frequency', 20)->default('maand'); // week | 4weken | maand | kwartaal | halfjaar | jaar
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_invoice_date')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->string('invoice_subject')->nullable();
            $table->string('client_reference')->nullable();
            $table->longText('description')->nullable();
            $table->decimal('total_excl', 14, 2)->default(0);
            $table->decimal('total_vat', 14, 2)->default(0);
            $table->decimal('total_incl', 14, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            // Een concept heeft nog geen nummer; het nummer wordt bij definitief maken toegekend.
            $table->unsignedBigInteger('number')->nullable()->unique();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credit_of_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->longText('description')->nullable();
            $table->string('client_reference')->nullable();
            $table->date('date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('concept'); // concept | verzonden
            $table->timestamp('sent_at')->nullable();
            $table->unsignedTinyInteger('reminder_level')->default(0); // 0 factuur, 1 herinnering, 2 tweede herinnering, 3 aanmaning
            $table->timestamp('last_reminder_at')->nullable();
            $table->string('payment_method', 20)->default('overboeking');
            $table->json('extra_fields')->nullable();
            $table->decimal('total_excl', 14, 2)->default(0);
            $table->decimal('total_vat', 14, 2)->default(0);
            $table->decimal('total_incl', 14, 2)->default(0);
            $table->decimal('total_paid', 14, 2)->default(0);
            $table->decimal('total_open', 14, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'due_date']);
            $table->index('date');
        });

        foreach (['contract_lines' => 'contract_id:contracts', 'invoice_lines' => 'invoice_id:invoices'] as $name => $parent) {
            [$column, $parentTable] = explode(':', $parent);
            Schema::create($name, function (Blueprint $table) use ($column, $parentTable, $name) {
                $table->id();
                $table->gripp();
                $table->foreignId($column)->constrained($parentTable)->cascadeOnDelete();
                $table->unsignedInteger('sort')->default(0);
                $table->string('row_type', 10)->default('normaal');
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->string('title', 500)->nullable();
                $table->text('description')->nullable();
                $table->decimal('quantity', 14, 4)->default(1);
                $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('unit_price', 14, 4)->default(0);
                $table->decimal('buying_price', 14, 4)->default(0);
                $table->decimal('discount', 5, 2)->default(0);
                $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
                $table->decimal('total_excl', 14, 2)->default(0);
                $table->text('internal_note')->nullable();
                $table->foreignId('ledger_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
                if ($name === 'invoice_lines') {
                    $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                    $table->foreignId('project_line_id')->nullable()->constrained()->nullOnDelete();
                    $table->foreignId('contract_line_id')->nullable()->constrained()->nullOnDelete();
                }
                $table->timestamps();
                $table->index([$column, 'sort']);
            });
        }

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 14, 2);
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('hours', function (Blueprint $table) {
            $table->foreignId('invoice_line_id')->nullable()->after('task_id')->constrained()->nullOnDelete();
        });
        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('task_id')->constrained()->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_id');
            $table->dropConstrainedForeignId('invoice_id');
        });
        Schema::table('hours', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_line_id');
        });
        foreach (['payments', 'invoice_lines', 'contract_lines', 'invoices', 'contracts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
