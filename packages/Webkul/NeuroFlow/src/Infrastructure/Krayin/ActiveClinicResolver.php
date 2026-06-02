<?php

namespace Webkul\NeuroFlow\Infrastructure\Krayin;

use Illuminate\Http\Request;

class ActiveClinicResolver
{
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

        return new ActiveClinic(
            id: (string) config('neuroflow.demo_clinic_id'),
            name: (string) config('neuroflow.demo_clinic_name'),
            timezone: (string) config('neuroflow.demo_clinic_timezone'),
            userId: (int) $user->id,
        );
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
