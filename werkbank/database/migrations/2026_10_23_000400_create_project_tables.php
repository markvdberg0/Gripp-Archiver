<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 3: opdrachten met regels, taken, agenda (planning) en uren.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['project_phases', 'task_phases', 'task_types'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->gripp();
                $table->string('name', 100);
                $table->string('color', 20)->nullable();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->unsignedBigInteger('number')->unique();
            $table->string('name');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('phase_id')->nullable()->constrained('project_phases')->nullOnDelete();
            $table->foreignId('account_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('deadline')->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('client_reference')->nullable();
            $table->string('work_address')->nullable();
            $table->longText('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->json('extra_fields')->nullable();
            $table->decimal('total_excl', 14, 2)->default(0);
            $table->decimal('total_vat', 14, 2)->default(0);
            $table->decimal('total_incl', 14, 2)->default(0);
            $table->boolean('archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employee_project', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'employee_id']);
        });

        Schema::create('project_lines', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
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
            $table->timestamps();
            $table->index(['project_id', 'sort']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->unsignedBigInteger('number')->nullable();
            $table->string('title', 500);
            $table->longText('description')->nullable();
            $table->foreignId('type_id')->nullable()->constrained('task_types')->nullOnDelete();
            $table->foreignId('phase_id')->nullable()->constrained('task_phases')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('deadline')->nullable();
            $table->time('deadline_time')->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['completed', 'deadline']);
        });

        Schema::create('calendar_items', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->decimal('hours', 6, 2)->default(0);
            $table->string('subject')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['date', 'employee_id']);
        });

        Schema::create('hours', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->date('date');
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('hours', 6, 2);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('concept'); // concept | definitief | gefiatteerd
            $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'date']);
            $table->index(['project_id', 'status']);
        });

        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('offer_id')->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_id');
            $table->dropConstrainedForeignId('project_id');
        });
        foreach (['hours', 'calendar_items', 'tasks', 'project_lines', 'employee_project', 'projects', 'task_types', 'task_phases', 'project_phases'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
