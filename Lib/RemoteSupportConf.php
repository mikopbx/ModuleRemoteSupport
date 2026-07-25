<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use MikoPBX\Core\System\Processes;
use MikoPBX\Core\System\SystemMessages;
use MikoPBX\Core\Workers\Cron\WorkerSafeScriptsCore;
use MikoPBX\Modules\Config\ConfigClass;
use Modules\ModuleRemoteSupport\bin\WorkerRemoteSupportTunnel;
use Throwable;

class RemoteSupportConf extends ConfigClass
{
    /**
     * @return list<array{type: string, worker: class-string}>
     */
    public function getModuleWorkers(): array
    {
        return [
            [
                'type' => WorkerSafeScriptsCore::CHECK_BY_PID_NOT_ALERT,
                'worker' => WorkerRemoteSupportTunnel::class,
            ],
        ];
    }

    public function onBeforeModuleEnable(): bool
    {
        try {
            $this->lifecycleManager()->preflight();

            return true;
        } catch (Throwable) {
            $this->messages[] = 'Remote support capability preflight failed';

            return false;
        }
    }

    public function onAfterModuleEnable(): void
    {
        Processes::processPHPWorker(
            WorkerRemoteSupportTunnel::class,
            action: 'start',
        );
    }

    public function onBeforeModuleDisable(): bool
    {
        try {
            $this->lifecycleManager()->disableAndRevoke();

            return true;
        } catch (Throwable) {
            $this->messages[] = 'Remote support access could not be revoked';

            return false;
        }
    }

    public function onAfterModuleDisable(): void
    {
        Processes::processPHPWorker(
            WorkerRemoteSupportTunnel::class,
            action: 'stop',
        );
    }

    /**
     * Authenticates the support engineer with the ephemeral web credential of
     * the active session.
     *
     * @param string $login The user login entered on the login page.
     * @param string $password The user password entered on the login page.
     *
     * @return array<string, string> The session data, empty when not ours.
     */
    public function authenticateUser(string $login, string $password): array
    {
        try {
            return (new WebAccessAuthenticator())->authenticate($login, $password);
        } catch (Throwable) {
            return [];
        }
    }

    public function onAfterPbxStarted(): void
    {
        try {
            $this->lifecycleManager()->recoverAfterPbxStart();
        } catch (Throwable) {
            SystemMessages::sysLogMsg(
                __CLASS__,
                'Remote support startup cleanup failed',
                LOG_ERR,
            );
        }
    }

    protected function lifecycleManager(): ModuleLifecycleManager
    {
        return new ModuleLifecycleManager();
    }
}
