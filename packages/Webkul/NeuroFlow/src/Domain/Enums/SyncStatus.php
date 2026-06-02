<?php

namespace Webkul\NeuroFlow\Domain\Enums;

enum SyncStatus: string
{
    case Fresh = 'fresh';
    case Stale = 'stale';
    case Failed = 'failed';
    case Disabled = 'disabled';
}
