<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained();
            $table->foreignId('instructor_id')->constrained('users');
            $table->integer('amount_cents'); // signed: negative for refund adjustments
            $table->string('type')->default('earning'); // earning|refund_adjustment
            $table->foreignId('payout_id')->nullable()->constrained('instructor_payouts')->nullOnDelete();
            $table->timestamp('earned_at');
            $table->timestamps();
            $table->index(['instructor_id', 'payout_id']);
            $table->index(['subscription_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_earnings');
    }
};
