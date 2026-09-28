<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users');
            $table->foreignId('plan_id')->constrained('subscription_plans');
            $table->unsignedInteger('amount_cents');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status')->default('active'); // active|refunded|cancelled|expired
            $table->timestamp('refunded_at')->nullable();
            $table->integer('refunded_amount_cents')->nullable();
            $table->timestamps();
            $table->index(['status','student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
