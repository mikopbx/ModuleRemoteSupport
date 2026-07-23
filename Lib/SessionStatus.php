<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

enum SessionStatus: string
{
    case OFF = 'off';
    case STARTING = 'starting';
    case ACTIVE = 'active';
    case STOPPING = 'stopping';
    case ERROR = 'error';
}
