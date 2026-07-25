<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

final readonly class GeneratedWebCredential
{
    public function __construct(
        public string $login,
        public string $password,
        public string $passwordHash,
    ) {
    }
}
