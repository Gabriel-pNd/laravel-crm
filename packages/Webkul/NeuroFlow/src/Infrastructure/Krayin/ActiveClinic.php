<?php

namespace Webkul\NeuroFlow\Infrastructure\Krayin;

class ActiveClinic
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $timezone,
        public readonly int $userId,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'user_id' => $this->userId,
        ];
    }
}
