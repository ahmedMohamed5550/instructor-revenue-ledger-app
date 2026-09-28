<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users');
            $table->string('period_key');
            $table->integer('amount_cents');
            $table->string('status')->default('processing'); // processing|succeeded|failed|unknown
            $table->uuid('idempotency_key')->unique();
            $table->string('provider_reference')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['instructor_id', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_payouts');
    }
};
