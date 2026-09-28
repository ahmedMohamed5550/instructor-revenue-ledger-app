<?php

use App\Http\Controllers\Api\InstructorLedgerController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::post('/subscriptions', [SubscriptionController::class, 'store']);
Route::post('/subscriptions/{subscription}/refund', [SubscriptionController::class, 'refund']);

Route::get('/instructors/{instructor}/balance', [InstructorLedgerController::class, 'balance']);
Route::get('/instructors/{instructor}/payouts', [InstructorLedgerController::class, 'payouts']);
