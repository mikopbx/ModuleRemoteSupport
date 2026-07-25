<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

interface RemoteSupportRuntimeInterface
{
    public function preflight(): void;

    public function create(): void;

    public function generateKeyPair(): void;

    public function writeKnownHosts(): void;

    public function privateKeyPath(): string;

    public function knownHostsPath(): string;

    public function writeWebCredential(string $login, string $password): void;

    /**
     * @return null|array{login: string, password: string}
     */
    public function readWebCredential(): ?array;

    public function cleanup(): void;

    public function cleanupStale(): void;
}
