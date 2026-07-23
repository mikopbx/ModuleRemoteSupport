<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use DateTimeImmutable;

final readonly class LobbyAllocation
{
    public function __construct(
        public string $code,
        public int $slot,
        public int $tunnelPort,
        public string $tunnelUser,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
