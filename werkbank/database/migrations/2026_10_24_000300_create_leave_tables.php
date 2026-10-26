<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Verlof: aanvragen met per dag een regel (datum + uren), goedkeuring door de administratie en een jaarsaldo.
// Ziekteverzuim hoort hier bewust niet bij (gezondheidsgegevens, AVG).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('leave_hours_per_year', 6, 2)->nullable(); // leeg = standaard uit de instellingen
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->foreignId('employee_id')->constrained();
            $table->string('type', 20)->default('vakantie');
            $table->string('description')->nullable();
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('aangevraagd'); // aangevraagd | goedgekeurd | afgewezen | ingetrokken | onbekend
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'status']);
        });

        Schema::create('leave_request_lines', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('hours', 5, 2);
            $table->timestamps();
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_lines');
        Schema::dropIfExists('leave_requests');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('leave_hours_per_year');
        });
    }
};
