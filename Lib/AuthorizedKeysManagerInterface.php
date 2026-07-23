<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

interface AuthorizedKeysManagerInterface
{
    public function install(string $sessionId): void;

    public function removeManagedKeys(): void;
}
