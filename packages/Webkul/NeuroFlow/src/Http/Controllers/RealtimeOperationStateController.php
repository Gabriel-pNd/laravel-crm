<?php

namespace Webkul\NeuroFlow\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\NeuroFlow\Application\Actions\GetRealtimeOperationState;

class RealtimeOperationStateController
{
    public function show(Request $request, GetRealtimeOperationState $state): JsonResponse
    {
        $clinic = $request->attributes->get('neuroflow.active_clinic');

        return response()->json($state->execute($clinic));
    }
}
