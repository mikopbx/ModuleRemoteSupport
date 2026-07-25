<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

interface SshProcessInterface
{
    public function allocate(string $privateKey, string $knownHosts): string;

    public function startTunnel(
        LobbyAllocation $allocation,
        string $privateKey,
        string $knownHosts,
        ?WebForwardTarget $webTarget = null,
    ): ProcessHandle;
}
