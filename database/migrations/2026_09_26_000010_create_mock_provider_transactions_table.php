<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_provider_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->integer('amount_cents');
            $table->string('status'); // succeeded|failed
            $table->string('reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_provider_transactions');
    }
};
