<?php

use App\Http\Controllers\MidtransNotificationController;
use Illuminate\Support\Facades\Route;

// Set as the Payment Notification URL in the Midtrans dashboard.
Route::post('/midtrans/notification', MidtransNotificationController::class);
