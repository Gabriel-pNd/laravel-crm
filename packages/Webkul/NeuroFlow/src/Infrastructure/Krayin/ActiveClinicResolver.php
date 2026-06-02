<?php

namespace Webkul\NeuroFlow\Infrastructure\Krayin;

use Illuminate\Http\Request;
use Webkul\NeuroFlow\Domain\Contracts\ActiveClinicMemberships;

class ActiveClinicResolver
{
    public function __construct(private readonly ActiveClinicMemberships $memberships)
    {
    }

    public function resolve(Request $request): ?ActiveClinic
    {
        $user = $request->user('user') ?? auth()->guard('user')->user();

        if (! $user || ! (bool) $user->status) {
            return null;
        }

        if (! $this->hasNeuroFlowPermission($user)) {
            return null;
        }

        if (! config('neuroflow.demo_mode')) {
            return null;
        }

        $requestedClinicId = (string) ($request->input('clinic_id') ?: config('neuroflow.demo_clinic_id'));

        return $this->memberships->activeClinicFor($user, $requestedClinicId);
    }

    private function hasNeuroFlowPermission(object $user): bool
    {
        if (! $role = $user->role) {
            return false;
        }

        if ($role->permission_type === 'all') {
            return true;
        }

        $permissions = $role->permissions ?? [];

        return in_array('neuroflow', $permissions, true)
            || in_array('neuroflow.realtime_operation', $permissions, true);
    }
}
