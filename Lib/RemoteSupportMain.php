<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use Closure;
use JsonException;
use MikoPBX\Core\System\BeanstalkClient;
use Modules\ModuleRemoteSupport\bin\WorkerRemoteSupportTunnel;
use Modules\ModuleRemoteSupport\Models\RemoteSupportSession;

final class RemoteSupportMain
{
    /** @var Closure(string, string): void */
    private readonly Closure $publish;

    /** @var Closure(): string */
    private readonly Closure $sessionIdFactory;

    /**
     * @param null|Closure(string, string): void $publish
     * @param null|Closure(): string $sessionIdFactory
     */
    public function __construct(
        private readonly SessionRepository $repository = new SessionRepository(),
        ?Closure $publish = null,
        ?Closure $sessionIdFactory = null,
    ) {
        $this->publish = $publish ?? self::publishCommand(...);
        $this->sessionIdFactory = $sessionIdFactory
            ?? static fn(): string => bin2hex(random_bytes(16));
    }

    public function requestStart(): RemoteSupportSession
    {
        $current = $this->repository->get();
        if (
            $current->status === SessionStatus::STARTING->value
            || $current->status === SessionStatus::ACTIVE->value
        ) {
            return $current;
        }

        $sessionId = ($this->sessionIdFactory)();
        $session = $this->repository->beginStart($sessionId);
        ($this->publish)('start', (string)$session->session_id);

        return $session;
    }

    public function requestStop(): RemoteSupportSession
    {
        $current = $this->repository->get();
        if (
            $current->status === SessionStatus::OFF->value
            || $current->status === SessionStatus::STOPPING->value
        ) {
            return $current;
        }

        $session = $this->repository->beginStop();
        ($this->publish)('stop', (string)$session->session_id);

        return $session;
    }

    /**
     * @throws JsonException
     */
    private static function publishCommand(string $command, string $sessionId): void
    {
        $client = new BeanstalkClient(WorkerRemoteSupportTunnel::class);
        $client->publish(
            json_encode(
                [
                    'command' => $command,
                    'session_id' => $sessionId,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );
    }
}
