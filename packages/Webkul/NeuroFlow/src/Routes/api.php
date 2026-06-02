<?php

use Illuminate\Support\Facades\Route;
use Webkul\NeuroFlow\Http\Controllers\RealtimeOperationStateController;

Route::get('realtime-operation/state', [RealtimeOperationStateController::class, 'show'])
    ->name('admin.neuroflow.api.realtime-operation.state');
