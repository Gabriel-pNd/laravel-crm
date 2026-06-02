<?php

namespace Webkul\NeuroFlow\Domain\Contracts;

use Webkul\NeuroFlow\Infrastructure\Krayin\ActiveClinic;

interface RealtimeOperationReadModel
{
    public function stateFor(ActiveClinic $clinic): array;
}
