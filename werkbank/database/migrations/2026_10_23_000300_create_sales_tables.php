<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 2: instellingen, stamgegevens (btw, eenheden, grootboek, kostenplaatsen), producten, pakketten en offertes.
// Bedragen: prijzen per eenheid met 4 decimalen, regel- en documenttotalen met 2 decimalen.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('vat_rates', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->string('name', 100);
            $table->decimal('percentage', 5, 2);
            $table->string('kind', 10)->default('beide'); // verkoop | inkoop | beide
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->string('name', 50);
            $table->string('type', 20)->default('aantal'); // aantal | uren
            $table->decimal('hours_per_unit', 8, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->string('number', 20);
            $table->string('name');
            $table->string('type', 20)->nullable(); // resultaat | balans
            $table->string('side', 10)->nullable(); // debet | credit
            $table->string('category')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index('number');
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->string('number', 20)->nullable();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->unsignedBigInteger('number')->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('internal_note')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('selling_price', 14, 4)->default(0);
            $table->decimal('buying_price', 14, 4)->default(0);
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->foreignId('purchase_vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->foreignId('ledger_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_ledger_id')->nullable()->constrained('ledgers')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('supplier_code', 100)->nullable();
            $table->string('invoice_basis', 20)->default('vast'); // vast | nacalculatie
            $table->json('extra_fields')->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('packets', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->unsignedBigInteger('number')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('archived')->default(false);
            $table->timestamps();
        });

        Schema::create('offer_phases', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->string('name', 100);
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            // Sjablonen (Gripp: basisoffertes) hebben geen nummer en geen relatie.
            $table->unsignedBigInteger('number')->nullable()->unique();
            $table->boolean('is_template')->default(false);
            $table->string('name');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('phase_id')->nullable()->constrained('offer_phases')->nullOnDelete();
            $table->foreignId('account_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('concept'); // concept | verzonden
            $table->string('acceptance', 20)->nullable(); // geaccepteerd | afgewezen
            $table->timestamp('accepted_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->date('date');
            $table->date('valid_until')->nullable();
            $table->date('deadline')->nullable();
            $table->unsignedTinyInteger('chance')->nullable();
            $table->string('client_reference')->nullable();
            $table->longText('description')->nullable();
            $table->string('template')->nullable();
            $table->json('extra_fields')->nullable();
            $table->decimal('total_excl', 14, 2)->default(0);
            $table->decimal('total_vat', 14, 2)->default(0);
            $table->decimal('total_incl', 14, 2)->default(0);
            $table->boolean('archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'acceptance']);
            $table->index('date');
        });

        // Documentregels: zelfde opbouw voor pakketten, offertes en (later) opdrachten en facturen.
        foreach (['packet_lines' => 'packet_id:packets', 'offer_lines' => 'offer_id:offers'] as $name => $parent) {
            [$column, $parentTable] = explode(':', $parent);
            Schema::create($name, function (Blueprint $table) use ($column, $parentTable) {
                $table->id();
                $table->gripp();
                $table->foreignId($column)->constrained($parentTable)->cascadeOnDelete();
                $table->unsignedInteger('sort')->default(0);
                $table->string('row_type', 10)->default('normaal'); // normaal | groep | tekst
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
                $table->timestamps();
                $table->index([$column, 'sort']);
            });
        }

        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->foreignId('offer_id')->nullable()->after('contact_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offer_id');
        });
        foreach (['offer_lines', 'packet_lines', 'offers', 'offer_phases', 'packets', 'products', 'cost_centers', 'ledgers', 'units', 'vat_rates', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
