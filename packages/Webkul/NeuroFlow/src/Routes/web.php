<?php

use Illuminate\Support\Facades\Route;
use Webkul\NeuroFlow\Http\Controllers\RealtimeOperationController;

Route::controller(RealtimeOperationController::class)->prefix('neuroflow')->group(function () {
    Route::get('realtime-operation', 'index')->name('admin.neuroflow.realtime-operation.index');
});
