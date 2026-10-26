<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 6: memoriaalboekingen en kosten uit Gripp, als alleen-lezen archief (Werkbank is geen boekhouding).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memorials', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->unsignedBigInteger('number')->nullable();
            $table->string('name')->nullable();
            $table->string('type', 50)->nullable();
            $table->date('date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('memorial_lines', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->foreignId('memorial_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('costs', function (Blueprint $table) {
            $table->id();
            $table->gripp();
            $table->unsignedBigInteger('number')->nullable();
            $table->date('date')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('description')->nullable();
            $table->foreignId('ledger_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costs');
        Schema::dropIfExists('memorial_lines');
        Schema::dropIfExists('memorials');
    }
};
