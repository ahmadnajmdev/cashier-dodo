<?php

use Ahmadnajmdev\Cashier\Dodo\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhook', WebhookController::class)->name('webhook');
