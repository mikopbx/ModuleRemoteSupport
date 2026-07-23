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
