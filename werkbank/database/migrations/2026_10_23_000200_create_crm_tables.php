<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 1: medewerkers, labels, relaties, contactpersonen, bestanden, timeline en importruns.
// Elke tabel die uit een Gripp-back-up kan komen heeft gripp_id (uniek), gripp_hash (hash van het
// bronrecord) en gripp_synced_at. Zo is een herhaalde import idempotent en zien we lokale wijzigingen.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->unsignedInteger('number')->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('infix', 30)->nullable();
            $table->string('last_name', 100);
            $table->string('initials', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->string('function', 100)->nullable();
            $table->date('employed_since')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->unsignedBigInteger('number')->nullable()->unique();
            $table->string('relation_type', 20)->default('bedrijf'); // bedrijf | particulier
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->boolean('is_customer')->default(false);
            $table->boolean('is_supplier')->default(false);
            $table->boolean('is_prospect')->default(false);
            $table->boolean('is_lead')->default(false);
            $table->string('email')->nullable();
            $table->string('invoice_email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->string('website')->nullable();
            $table->string('coc_number', 20)->nullable();
            $table->string('vat_number', 30)->nullable();
            $table->boolean('vat_shifted')->default(false);
            $table->string('iban', 40)->nullable();
            $table->string('bic', 15)->nullable();
            $table->string('bank_account_holder')->nullable();
            foreach (['visit', 'postal', 'invoice'] as $p) {
                $table->string("{$p}_street")->nullable();
                $table->string("{$p}_house_number", 20)->nullable();
                $table->string("{$p}_postcode", 15)->nullable();
                $table->string("{$p}_city", 100)->nullable();
                $table->string("{$p}_country", 60)->nullable();
            }
            $table->string('invoice_company_name')->nullable();
            $table->string('invoice_attention')->nullable();
            $table->string('invoice_send_by', 10)->default('email'); // email | post
            $table->string('payment_method', 20)->default('overboeking'); // overboeking | incasso | online
            $table->unsignedSmallInteger('payment_term_days')->nullable();
            $table->unsignedSmallInteger('supplier_payment_term_days')->nullable();
            $table->date('founded_on')->nullable();
            $table->foreignId('account_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('name');
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('salutation', 20)->nullable(); // dhr | mevr | dhr_mevr
            $table->string('initials', 20)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('infix', 30)->nullable();
            $table->string('last_name', 100);
            $table->string('function', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->boolean('show_on_company')->default(true);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('last_name');
        });

        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->string('name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->char('sha256', 64)->nullable()->index();
            // Pad op de schijf 'local' (privé), relatief. Leeg als de inhoud (nog) ontbreekt.
            $table->string('path')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->morphs('attachable');
            $table->string('role', 30)->default('bijlage'); // bijlage | document
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['file_id', 'attachable_type', 'attachable_id', 'role']);
        });

        Schema::create('timeline_entries', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            // notitie | telefoon | email_ontvangen | email_verzonden | systeem
            $table->string('type', 20)->default('notitie');
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->boolean('starred')->default(false);
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_path', 500);
            $table->string('snapshot', 100)->nullable();
            $table->boolean('dry_run')->default(true);
            $table->string('only', 500)->nullable();
            $table->string('status', 20)->default('wachtrij'); // wachtrij | bezig | geslaagd | mislukt
            $table->json('summary')->nullable();
            $table->json('issues')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['import_runs', 'timeline_entries', 'attachments', 'files', 'contacts', 'companies', 'taggables', 'tags', 'employees'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
