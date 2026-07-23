<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use Closure;
use RuntimeException;

final class ModuleLifecycleManager
{
    private const int STOP_WAIT_ATTEMPTS = 50;
    private const int STOP_WAIT_MICROSECONDS = 200_000;

    /** @var Closure(): void */
    private readonly Closure $sleep;

    /**
     * @param null|Closure(): void $sleep
     */
    public function __construct(
        private readonly SessionRepository $repository = new SessionRepository(),
        private readonly RemoteSupportMain $main = new RemoteSupportMain(),
        private readonly RemoteSupportRuntimeInterface $runtime = new RuntimeDirectory(),
        private readonly AuthorizedKeysManagerInterface $authorizedKeys = new AuthorizedKeysManager(),
        ?Closure $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (): void {
            usleep(self::STOP_WAIT_MICROSECONDS);
        };
    }

    public function preflight(): void
    {
        $this->runtime->preflight();
    }

    public function disableAndRevoke(): void
    {
        $session = $this->main->requestStop();
        if ($session->status !== SessionStatus::OFF->value) {
            $this->waitUntilOff();
        }

        $this->removeResidualAccess();
    }

    public function recoverAfterPbxStart(): void
    {
        $this->removeResidualAccess();
    }

    public function cleanupBeforeUninstall(): void
    {
        $this->disableAndRevoke();
    }

    private function waitUntilOff(): void
    {
        for ($attempt = 0; $attempt < self::STOP_WAIT_ATTEMPTS; $attempt++) {
            if ($this->repository->get()->status === SessionStatus::OFF->value) {
                return;
            }

            ($this->sleep)();
        }

        throw new RuntimeException('Timed out while revoking remote support access');
    }

    private function removeResidualAccess(): void
    {
        $this->authorizedKeys->removeManagedKeys();
        $this->runtime->cleanupStale();
        $this->repository->resetToOff();
    }
}
