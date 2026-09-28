<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_course_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained();
            $table->foreignId('instructor_id')->constrained('users');
            $table->timestamps();
            $table->unique(['subscription_id', 'course_id']);
            $table->index(['subscription_id', 'instructor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_course_access');
    }
};
