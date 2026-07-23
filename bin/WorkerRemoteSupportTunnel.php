<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\bin;

require_once 'Globals.php';

use JsonException;
use MikoPBX\Common\Handlers\CriticalErrorsHandler;
use MikoPBX\Core\System\BeanstalkClient;
use MikoPBX\Core\Workers\WorkerBase;
use Modules\ModuleRemoteSupport\Lib\RemoteSupportTunnelLifecycle;
use Throwable;

class WorkerRemoteSupportTunnel extends WorkerBase
{
    private const int CHECK_INTERVAL_SECONDS = 1;

    private RemoteSupportTunnelLifecycle $lifecycle;

    public static function getCheckInterval(): int
    {
        return self::CHECK_INTERVAL_SECONDS;
    }

    /**
     * @param list<string> $argv
     */
    public function start(array $argv): void
    {
        $this->lifecycle = new RemoteSupportTunnelLifecycle();
        $this->lifecycle->recoverStaleState();

        $client = new BeanstalkClient(self::class);
        $client->subscribe($this->makePingTubeName(self::class), [$this, 'pingCallBack']);
        $client->subscribe(self::class, [$this, 'beanstalkCallback']);

        while (!$this->needRestart) {
            pcntl_signal_dispatch();
            $client->wait(self::CHECK_INTERVAL_SECONDS);
            $this->lifecycle->tick();
        }

        $this->lifecycle->handleSignal();
    }

    /**
     * @throws JsonException
     */
    public function beanstalkCallback(BeanstalkClient $message): void
    {
        $payload = json_decode($message->getBody(), true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            return;
        }

        $command = $payload['command'] ?? null;
        $sessionId = $payload['session_id'] ?? null;
        if ($command === 'start' && is_string($sessionId)) {
            $this->lifecycle->start($sessionId);
        } elseif ($command === 'stop') {
            $this->lifecycle->stop();
        }
    }
}

$workerClassname = WorkerRemoteSupportTunnel::class;
if (isset($argv) && count($argv) > 1) {
    cli_set_process_title($workerClassname);
    try {
        $worker = new $workerClassname();
        $worker->start($argv);
    } catch (Throwable $exception) {
        CriticalErrorsHandler::handleExceptionWithSyslog($exception);
    }
}
