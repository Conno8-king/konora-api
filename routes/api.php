<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\MyTicketController;
use App\Http\Controllers\Api\OrganizerAnalyticsController;
use App\Http\Controllers\Api\OrganizerScanController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketTierController;
use App\Http\Controllers\Api\TicketValidationController;
use App\Http\Controllers\Api\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show'])->whereNumber('id');

Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user/profile', [UserProfileController::class, 'show']);
    Route::patch('/user/profile', [UserProfileController::class, 'update']);
    Route::patch('/user/password', [UserProfileController::class, 'updatePassword']);

    Route::get('/me/tickets', [MyTicketController::class, 'index']);
    Route::get('/me/tickets/{ticket}', [MyTicketController::class, 'show']);

    Route::get('/payments/verify/{reference}', [PaymentController::class, 'verify']);

    Route::get('/tickets/my-tickets', [TicketController::class, 'myTickets']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->whereNumber('ticket');
});

Route::middleware(['auth:sanctum', 'user'])->group(function (): void {
    Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
});

Route::middleware(['auth:sanctum', 'organizer'])->group(function (): void {
    Route::get('/organizer/events', [EventController::class, 'organizerIndex']);
    Route::get('/organizer/events/{event}', [EventController::class, 'organizerShow'])
        ->whereNumber('event');
    Route::get('/organizer/stats', [OrganizerAnalyticsController::class, 'stats']);
    Route::get('/organizer/analytics/{event}', [OrganizerAnalyticsController::class, 'analytics'])
        ->whereNumber('event');

    Route::post('/tickets/validate', [TicketValidationController::class, 'validateTicket'])
        ->middleware('throttle:ticket-validate');
    Route::get('/organizer/scans', [OrganizerScanController::class, 'index']);
    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{event}', [EventController::class, 'update']);
    Route::delete('/events/{event}', [EventController::class, 'destroy']);
    Route::patch('/events/{event}/publish', [EventController::class, 'publish']);
    Route::patch('/events/{event}/end', [EventController::class, 'end']);

    Route::post('/events/{event}/tiers', [TicketTierController::class, 'store']);
    Route::put('/tiers/{tier}', [TicketTierController::class, 'update']);
    Route::delete('/tiers/{tier}', [TicketTierController::class, 'destroy']);
});

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::patch('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});
