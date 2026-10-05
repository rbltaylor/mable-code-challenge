<?php

use App\Http\Controllers\TransactionBatchController;
use Illuminate\Support\Facades\Route;

Route::post('/transaction-batches', [TransactionBatchController::class, 'create'])->middleware('company.api');
