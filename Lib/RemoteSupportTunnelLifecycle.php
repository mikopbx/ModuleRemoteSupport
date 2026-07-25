<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use Closure;
use Modules\ModuleRemoteSupport\Models\RemoteSupportSession;
use Throwable;

final class RemoteSupportTunnelLifecycle
{
    private const int TUNNEL_CONFIRM_MILLISECONDS = 1_000;

    private ?ProcessHandle $tunnel = null;

    /** @var Closure(): int */
    private readonly Closure $clock;

    /** @var Closure(ProcessHandle): bool */
    private readonly Closure $confirmTunnel;

    /** @var Closure(): ?WebForwardTarget */
    private readonly Closure $webTargetResolver;

    /**
     * @param null|Closure(): int $clock
     * @param null|Closure(ProcessHandle): bool $confirmTunnel
     * @param null|Closure(): ?WebForwardTarget $webTargetResolver
     */
    public function __construct(
        private readonly SessionRepository $repository = new SessionRepository(),
        private readonly RemoteSupportRuntimeInterface $runtime = new RuntimeDirectory(),
        private readonly AuthorizedKeysManagerInterface $authorizedKeys = new AuthorizedKeysManager(),
        private readonly SshProcessInterface $ssh = new SshProcess(),
        ?Closure $clock = null,
        ?Closure $confirmTunnel = null,
        ?Closure $webTargetResolver = null,
        private readonly WebCredentialGenerator $webCredentials = new WebCredentialGenerator(),
    ) {
        $this->clock = $clock ?? time(...);
        $this->confirmTunnel = $confirmTunnel ?? $this->waitForTunnel(...);
        $this->webTargetResolver = $webTargetResolver
            ?? static fn(): ?WebForwardTarget => WebForwardTarget::fromPbxConfiguration();
    }

    public function start(string $sessionId): RemoteSupportSession
    {
        $session = $this->repository->beginStart($sessionId);
        if (
            $session->status === SessionStatus::ACTIVE->value
            || $session->session_id !== $sessionId
            || $this->tunnel !== null
        ) {
            return $session;
        }

        $errorCode = 'preflight_failed';
        try {
            $this->runtime->preflight();

            $errorCode = 'runtime_failed';
            $this->runtime->create();
            $this->runtime->generateKeyPair();
            $this->runtime->writeKnownHosts();

            $errorCode = 'allocation_failed';
            $protocol = new LobbyProtocol($this->clock);
            $allocation = $protocol->parse(
                $this->ssh->allocate(
                    $this->runtime->privateKeyPath(),
                    $this->runtime->knownHostsPath(),
                ),
            );

            $webTarget = null;
            $webLogin = '';
            $webPasswordHash = '';
            if ($allocation->webTunnelPort !== null) {
                $errorCode = 'web_credential_failed';
                // A station without a resolvable non-loopback admin address
                // degrades to an SSH-only session instead of failing support.
                $webTarget = ($this->webTargetResolver)();
                if ($webTarget !== null) {
                    $credential = $this->webCredentials->generate();
                    $this->runtime->writeWebCredential(
                        $credential->login,
                        $credential->password,
                    );
                    $webLogin = $credential->login;
                    $webPasswordHash = $credential->passwordHash;
                }
            }

            $errorCode = 'key_install_failed';
            $this->authorizedKeys->install($sessionId);

            $errorCode = 'tunnel_start_failed';
            $this->tunnel = $this->ssh->startTunnel(
                $allocation,
                $this->runtime->privateKeyPath(),
                $this->runtime->knownHostsPath(),
                $webTarget,
            );
            if (!(($this->confirmTunnel)($this->tunnel))) {
                $errorCode = 'tunnel_not_established';
                throw new TunnelLifecycleException('Reverse tunnel was not established');
            }

            $startedAt = ($this->clock)();

            return $this->repository->markActive(
                code: $allocation->code,
                slot: $allocation->slot,
                tunnelPort: $allocation->tunnelPort,
                startedAt: $startedAt,
                expiresAt: $startedAt + RemoteSupportConfig::SESSION_TTL_SECONDS,
                webLogin: $webLogin,
                webPasswordHash: $webPasswordHash,
            );
        } catch (Throwable) {
            return $this->failAndCleanup($errorCode);
        }
    }

    public function stop(): RemoteSupportSession
    {
        $this->repository->beginStop();

        return $this->cleanup();
    }

    public function tick(): RemoteSupportSession
    {
        $session = $this->repository->get();
        if ($session->status !== SessionStatus::ACTIVE->value) {
            return $session;
        }
        if ($this->tunnel === null || !$this->tunnel->isRunning()) {
            return $this->failAndCleanup('tunnel_disconnected');
        }
        if ((int)$session->expires_at <= ($this->clock)()) {
            return $this->stop();
        }

        return $session;
    }

    public function handleSignal(): RemoteSupportSession
    {
        return $this->stop();
    }

    public function recoverStaleState(): RemoteSupportSession
    {
        $this->stopTunnel();
        $this->authorizedKeys->removeManagedKeys();
        $this->runtime->cleanup();
        $this->runtime->cleanupStale();

        return $this->repository->resetToOff();
    }

    private function failAndCleanup(string $safeErrorCode): RemoteSupportSession
    {
        try {
            $this->cleanup();
        } catch (Throwable) {
            return $this->repository->markError('cleanup_failed');
        }

        return $this->repository->markError($safeErrorCode);
    }

    private function cleanup(): RemoteSupportSession
    {
        $this->stopTunnel();
        $this->authorizedKeys->removeManagedKeys();
        $this->runtime->cleanup();

        return $this->repository->resetToOff();
    }

    private function stopTunnel(): void
    {
        if ($this->tunnel === null) {
            return;
        }

        $this->tunnel->terminate();
        $this->tunnel = null;
    }

    private function waitForTunnel(ProcessHandle $handle): bool
    {
        $deadline = microtime(true) + (self::TUNNEL_CONFIRM_MILLISECONDS / 1_000);
        while (microtime(true) < $deadline) {
            if (!$handle->isRunning()) {
                return false;
            }
            usleep(50_000);
        }

        return $handle->isRunning();
    }
}
