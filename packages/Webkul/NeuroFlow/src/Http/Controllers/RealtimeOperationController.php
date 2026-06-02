<?php

namespace Webkul\NeuroFlow\Http\Controllers;

use Illuminate\Http\Request;
use Webkul\NeuroFlow\Application\Actions\GetRealtimeOperationState;

class RealtimeOperationController
{
    public function index(Request $request, GetRealtimeOperationState $state)
    {
        $clinic = $request->attributes->get('neuroflow.active_clinic');

        return view('neuroflow::realtime-operation.index', [
            'clinic' => $clinic,
            'state' => $state->execute($clinic),
            'desktopMinWidth' => (int) config('neuroflow.desktop_min_width_px'),
            'pollingIntervalSeconds' => (int) config('neuroflow.polling_interval_seconds'),
        ]);
    }
}
