<?php

namespace Webkul\NeuroFlow\Domain\Contracts;

use Webkul\NeuroFlow\Infrastructure\Krayin\ActiveClinic;

interface ActiveClinicMemberships
{
    public function activeClinicFor(object $user, string $clinicId): ?ActiveClinic;
}
