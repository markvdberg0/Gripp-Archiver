<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fase 5: inkoopopdrachten en inkoopfacturen met regels, controle en goedkeuring, en betalingen.
return new class extends Migration
{
    public function up(): void
    {
        // Btw verlegd (intracommunautair, import, verleggingsregeling): btw wordt berekend en getoond,
        // maar telt niet mee in het te betalen totaal.
        Schema::table('vat_rates', function (Blueprint $table) {
            $table->boolean('reverse_charge')->default(false)->after('kind');
        });
        // Al geïmporteerde tarieven met "binnen/buiten EU" of "verlegd" in de naam.
        DB::table('vat_rates')->where('percentage', '>', 0)
            ->where(fn ($q) => $q->where('name', 'like', '%binnen EU%')->orWhere('name', 'like', '%buiten EU%')->orWhere('name', 'like', '%verlegd%'))
            ->update(['reverse_charge' => true]);

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->unsignedBigInteger('number')->unique();
            $table->string('name');
            $table->foreignId('supplier_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete(); // besteller
            $table->string('status', 20)->default('concept'); // concept | verzonden | bevestigd | afgerond
            $table->date('date');
            $table->date('delivery_date')->nullable();
            $table->string('reference')->nullable();
            $table->string('delivery_address')->nullable();
            $table->longText('description')->nullable();
            $table->decimal('total_excl', 14, 2)->default(0);
            $table->decimal('total_vat', 14, 2)->default(0);
            $table->decimal('total_incl', 14, 2)->default(0);
            $table->boolean('archived')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'date']);
        });

        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->unsignedBigInteger('booking_number')->nullable()->unique(); // eigen volgnummer
            $table->string('invoice_number', 100)->nullable(); // nummer van de leverancier
            $table->foreignId('supplier_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject')->nullable();
            $table->longText('description')->nullable();
            $table->date('date')->nullable();
            $table->date('due_date')->nullable();
            // concept | controle | akkoord | verwerkt; on_hold staat apart zodat de fase bewaard blijft
            $table->string('phase', 20)->default('concept');
            $table->boolean('on_hold')->default(false);
            $table->string('hold_reason')->nullable();
            $table->boolean('inbox')->default(false); // Gripp: unprocessed (nog niet verwerkt uit de inbox)
            $table->decimal('expected_total', 14, 2)->nullable(); // totaal incl. btw volgens de factuur zelf
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('payment_method', 20)->default('overboeking');
            $table->decimal('total_excl', 14, 2)->default(0);
            $table->decimal('total_vat', 14, 2)->default(0);
            $table->decimal('total_incl', 14, 2)->default(0);
            $table->decimal('total_paid', 14, 2)->default(0);
            $table->decimal('total_open', 14, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['phase', 'due_date']);
            $table->index(['supplier_id', 'invoice_number']);
        });

        foreach (['purchase_order_lines' => 'purchase_order_id:purchase_orders', 'purchase_invoice_lines' => 'purchase_invoice_id:purchase_invoices'] as $name => $parent) {
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
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('project_line_id')->nullable()->constrained()->nullOnDelete();
                if ($name === 'purchase_order_lines') {
                    $table->string('supplier_code', 100)->nullable();
                } else {
                    $table->foreignId('purchase_order_line_id')->nullable()->constrained()->nullOnDelete();
                }
                $table->timestamps();
                $table->index([$column, 'sort']);
            });
        }

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 14, 2);
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->after('contract_id')->constrained()->nullOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->after('purchase_order_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_invoice_id');
            $table->dropConstrainedForeignId('purchase_order_id');
        });
        foreach (['purchase_payments', 'purchase_invoice_lines', 'purchase_order_lines', 'purchase_invoices', 'purchase_orders'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('vat_rates', function (Blueprint $table) {
            $table->dropColumn('reverse_charge');
        });
    }
};
