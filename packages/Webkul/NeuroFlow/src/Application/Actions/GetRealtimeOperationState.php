<?php

namespace Webkul\NeuroFlow\Application\Actions;

use Webkul\NeuroFlow\Domain\Contracts\RealtimeOperationReadModel;
use Webkul\NeuroFlow\Infrastructure\Krayin\ActiveClinic;

class GetRealtimeOperationState
{
    public function __construct(private readonly RealtimeOperationReadModel $readModel)
    {
    }

    public function execute(ActiveClinic $clinic): array
    {
        return $this->readModel->stateFor($clinic);
    }
}
