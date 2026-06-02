<?php

namespace Webkul\NeuroFlow\Http\Controllers;

use Illuminate\Http\Request;
use Webkul\NeuroFlow\Application\Actions\GetRealtimeOperationState;
use Webkul\NeuroFlow\Application\Presenters\RealtimeOperationPresenter;

class RealtimeOperationController
{
    public function index(Request $request, GetRealtimeOperationState $state, RealtimeOperationPresenter $presenter)
    {
        $clinic = $request->attributes->get('neuroflow.active_clinic');
        $rawState = $state->execute($clinic);

        return view('neuroflow::realtime-operation.index', [
            'clinic' => $clinic,
            'state' => $rawState,
            'cockpit' => $presenter->present($rawState),
            'desktopMinWidth' => (int) config('neuroflow.desktop_min_width_px'),
            'pollingIntervalSeconds' => (int) config('neuroflow.polling_interval_seconds'),
        ]);
    }
}
