<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tweestapsverificatie (TOTP). Geheim en herstelcodes worden versleuteld opgeslagen (APP_KEY);
// two_factor_last_step voorkomt dat dezelfde code twee keer wordt gebruikt.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']);
        });
    }
};
