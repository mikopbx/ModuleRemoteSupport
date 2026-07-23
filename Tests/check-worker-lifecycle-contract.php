<?php

declare(strict_types=1);

use Modules\ModuleRemoteSupport\Lib\AuthorizedKeysManagerInterface;
use Modules\ModuleRemoteSupport\Lib\LobbyAllocation;
use Modules\ModuleRemoteSupport\Lib\ProcessHandle;
use Modules\ModuleRemoteSupport\Lib\RemoteSupportMain;
use Modules\ModuleRemoteSupport\Lib\RemoteSupportRuntimeInterface;
use Modules\ModuleRemoteSupport\Lib\RemoteSupportTunnelLifecycle;
use Modules\ModuleRemoteSupport\Lib\SessionRepository;
use Modules\ModuleRemoteSupport\Lib\SessionStatus;
use Modules\ModuleRemoteSupport\Lib\SshProcessInterface;
use Modules\ModuleRemoteSupport\Models\RemoteSupportSession;

require_once __DIR__ . '/bootstrap.php';

final class FakeRemoteSupportRuntime implements RemoteSupportRuntimeInterface
{
    /** @var list<string> */
    private array $events;

    public bool $failCreate = false;

    /**
     * @param list<string> $events
     */
    public function __construct(array &$events)
    {
        $this->events =& $events;
    }

    public function preflight(): void
    {
        $this->events[] = 'preflight';
    }

    public function create(): void
    {
        $this->events[] = 'runtime:create';
        if ($this->failCreate) {
            throw new RuntimeException('create failed');
        }
    }

    public function generateKeyPair(): void
    {
        $this->events[] = 'runtime:keygen';
    }

    public function writeKnownHosts(): void
    {
        $this->events[] = 'runtime:known-hosts';
    }

    public function privateKeyPath(): string
    {
        return '/private/runtime/id_ed25519';
    }

    public function knownHostsPath(): string
    {
        return '/private/runtime/known_hosts';
    }

    public function cleanup(): void
    {
        $this->events[] = 'runtime:cleanup';
    }

    public function cleanupStale(): void
    {
        $this->events[] = 'runtime:cleanup-stale';
    }
}

final class FakeAuthorizedKeysManager implements AuthorizedKeysManagerInterface
{
    /** @var list<string> */
    private array $events;

    /**
     * @param list<string> $events
     */
    public function __construct(array &$events)
    {
        $this->events =& $events;
    }

    public function install(string $sessionId): void
    {
        $this->events[] = 'keys:install';
    }

    public function removeManagedKeys(): void
    {
        $this->events[] = 'keys:remove';
    }
}

final class FakeTunnelHandle extends ProcessHandle
{
    /** @var list<string> */
    private array $events;

    public bool $running = true;

    /**
     * @param list<string> $events
     */
    public function __construct(array &$events)
    {
        $this->events =& $events;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function terminate(int $graceMilliseconds = 1_000): void
    {
        $this->events[] = 'process:stop';
        $this->running = false;
    }
}

final class FakeSshProcess implements SshProcessInterface
{
    /** @var list<string> */
    private array $events;

    public bool $failAllocation = false;
    public bool $failTunnel = false;
    public bool $startRunning = true;
    public ?FakeTunnelHandle $handle = null;

    /**
     * @param list<string> $events
     */
    public function __construct(array &$events)
    {
        $this->events =& $events;
    }

    public function allocate(string $privateKey, string $knownHosts): string
    {
        $this->events[] = 'ssh:allocate';
        if ($this->failAllocation) {
            throw new RuntimeException('allocation failed');
        }

        return "MIKO-LOBBY: v1\n"
            . "CODE: ABC-123\n"
            . "SLOT: 42\n"
            . "TUNNEL_PORT: 22042\n"
            . "TUNNEL_USER: lobbytun\n"
            . "EXPIRES: 2026-07-23T12:00:00+00:00\n";
    }

    public function startTunnel(
        LobbyAllocation $allocation,
        string $privateKey,
        string $knownHosts,
    ): ProcessHandle {
        $this->events[] = 'ssh:start-tunnel';
        if ($this->failTunnel) {
            throw new RuntimeException('tunnel failed');
        }

        $this->handle = new FakeTunnelHandle($this->events);
        $this->handle->running = $this->startRunning;

        return $this->handle;
    }
}

/**
 * @param list<string> $events
 */
function lifecycleRepository(
    RemoteSupportSession &$stored,
    array &$events,
    int &$now,
): SessionRepository {
    $load = static function () use (&$stored): RemoteSupportSession {
        return clone $stored;
    };
    $save = static function (RemoteSupportSession $session) use (&$stored, &$events): void {
        $stored = clone $session;
        $events[] = 'state:' . $session->status;
    };

    return new SessionRepository(
        $load,
        $save,
        static fn(callable $callback): mixed => $callback(),
        static function () use (&$now): int {
            return $now;
        },
    );
}

function offSession(): RemoteSupportSession
{
    $session = new RemoteSupportSession();
    $session->id = 1;
    $session->status = SessionStatus::OFF->value;

    return $session;
}

$now = 1_721_721_600;
$clock = static function () use (&$now): int {
    return $now;
};
$events = [];
$stored = offSession();
$repository = lifecycleRepository($stored, $events, $now);
$runtime = new FakeRemoteSupportRuntime($events);
$keys = new FakeAuthorizedKeysManager($events);
$ssh = new FakeSshProcess($events);
$confirm = static fn(ProcessHandle $handle): bool => $handle->isRunning();
$lifecycle = new RemoteSupportTunnelLifecycle(
    $repository,
    $runtime,
    $keys,
    $ssh,
    $clock,
    $confirm,
);
$sessionId = '0123456789abcdef0123456789abcdef';

$active = $lifecycle->start($sessionId);
contractAssertSame(SessionStatus::ACTIVE->value, $active->status, 'off -> starting -> active');
contractAssertSame((string)$now, $active->started_at, 'active start uses local clock');
contractAssertSame(
    (string)($now + 28_800),
    $active->expires_at,
    'local expiry is exactly eight hours',
);

$startCount = count(array_filter($events, static fn(string $event): bool => $event === 'ssh:start-tunnel'));
$same = $lifecycle->start('ffffffffffffffffffffffffffffffff');
contractAssertSame($sessionId, $same->session_id, 'repeated start returns the same session');
contractAssertSame(
    $startCount,
    count(array_filter($events, static fn(string $event): bool => $event === 'ssh:start-tunnel')),
    'repeated start creates no second tunnel',
);

$stopOffset = count($events);
$lifecycle->stop();
$stopEvents = array_slice($events, $stopOffset);
contractAssertSame(
    ['state:stopping', 'process:stop', 'keys:remove', 'runtime:cleanup', 'state:off'],
    $stopEvents,
    'stop transition precedes cleanup ordered as process, managed key, runtime secrets, off',
);
$lifecycle->stop();
contractAssertSame(SessionStatus::OFF->value, $stored->status, 'cleanup is idempotent');

$failureCases = [
    'allocation_failed' => static function (FakeSshProcess $fake): void {
        $fake->failAllocation = true;
    },
    'tunnel_start_failed' => static function (FakeSshProcess $fake): void {
        $fake->failTunnel = true;
    },
    'tunnel_not_established' => static function (FakeSshProcess $fake): void {
        $fake->startRunning = false;
    },
];
foreach ($failureCases as $errorCode => $configure) {
    $caseEvents = [];
    $caseStored = offSession();
    $caseRepository = lifecycleRepository($caseStored, $caseEvents, $now);
    $caseRuntime = new FakeRemoteSupportRuntime($caseEvents);
    $caseKeys = new FakeAuthorizedKeysManager($caseEvents);
    $caseSsh = new FakeSshProcess($caseEvents);
    $configure($caseSsh);
    $caseLifecycle = new RemoteSupportTunnelLifecycle(
        $caseRepository,
        $caseRuntime,
        $caseKeys,
        $caseSsh,
        $clock,
        $confirm,
    );

    $failed = $caseLifecycle->start($sessionId);
    contractAssertSame(SessionStatus::ERROR->value, $failed->status, $errorCode . ' is visible');
    contractAssertSame($errorCode, $failed->error_code, $errorCode . ' is safe and stable');
    contractAssert(
        array_search('keys:remove', $caseEvents, true)
            < array_search('runtime:cleanup', $caseEvents, true),
        $errorCode . ' cleanup removes access before runtime secrets',
    );
}

$disconnectEvents = [];
$disconnectStored = offSession();
$disconnectRepository = lifecycleRepository($disconnectStored, $disconnectEvents, $now);
$disconnectSsh = new FakeSshProcess($disconnectEvents);
$disconnectLifecycle = new RemoteSupportTunnelLifecycle(
    $disconnectRepository,
    new FakeRemoteSupportRuntime($disconnectEvents),
    new FakeAuthorizedKeysManager($disconnectEvents),
    $disconnectSsh,
    $clock,
    $confirm,
);
$disconnectLifecycle->start($sessionId);
contractAssert($disconnectSsh->handle instanceof FakeTunnelHandle, 'tunnel handle exists');
$disconnectSsh->handle->running = false;
$disconnectLifecycle->tick();
contractAssertSame(SessionStatus::ERROR->value, $disconnectStored->status, 'disconnect becomes error');
contractAssertSame('tunnel_disconnected', $disconnectStored->error_code, 'disconnect uses safe code');

$ttlEvents = [];
$ttlStored = offSession();
$ttlRepository = lifecycleRepository($ttlStored, $ttlEvents, $now);
$ttlLifecycle = new RemoteSupportTunnelLifecycle(
    $ttlRepository,
    new FakeRemoteSupportRuntime($ttlEvents),
    new FakeAuthorizedKeysManager($ttlEvents),
    new FakeSshProcess($ttlEvents),
    $clock,
    $confirm,
);
$ttlLifecycle->start($sessionId);
$now += 28_800;
$ttlLifecycle->tick();
contractAssertSame(SessionStatus::OFF->value, $ttlStored->status, 'eight-hour TTL cleans to off');

$signalEvents = [];
$signalStored = offSession();
$signalRepository = lifecycleRepository($signalStored, $signalEvents, $now);
$signalLifecycle = new RemoteSupportTunnelLifecycle(
    $signalRepository,
    new FakeRemoteSupportRuntime($signalEvents),
    new FakeAuthorizedKeysManager($signalEvents),
    new FakeSshProcess($signalEvents),
    $clock,
    $confirm,
);
$signalLifecycle->start($sessionId);
$signalLifecycle->handleSignal();
contractAssertSame(SessionStatus::OFF->value, $signalStored->status, 'SIGTERM path cleans to off');

$partialEvents = [];
$partialStored = offSession();
$partialRepository = lifecycleRepository($partialStored, $partialEvents, $now);
$partialRuntime = new FakeRemoteSupportRuntime($partialEvents);
$partialRuntime->failCreate = true;
$partialLifecycle = new RemoteSupportTunnelLifecycle(
    $partialRepository,
    $partialRuntime,
    new FakeAuthorizedKeysManager($partialEvents),
    new FakeSshProcess($partialEvents),
    $clock,
    $confirm,
);
$partialLifecycle->start($sessionId);
contractAssert(
    in_array('keys:remove', $partialEvents, true) && in_array('runtime:cleanup', $partialEvents, true),
    'partial startup still enters common cleanup',
);

$recoveryEvents = [];
$recoveryStored = offSession();
$recoveryStored->status = SessionStatus::ACTIVE->value;
$recoveryStored->session_id = $sessionId;
$recoveryRepository = lifecycleRepository($recoveryStored, $recoveryEvents, $now);
$recoverySsh = new FakeSshProcess($recoveryEvents);
$recoveryLifecycle = new RemoteSupportTunnelLifecycle(
    $recoveryRepository,
    new FakeRemoteSupportRuntime($recoveryEvents),
    new FakeAuthorizedKeysManager($recoveryEvents),
    $recoverySsh,
    $clock,
    $confirm,
);
$recoveryLifecycle->recoverStaleState();
contractAssertSame(SessionStatus::OFF->value, $recoveryStored->status, 'startup recovery resets stale state');
contractAssert(
    !in_array('ssh:start-tunnel', $recoveryEvents, true),
    'startup recovery never reconnects',
);

$queueEvents = [];
$queueStored = offSession();
$queueRepository = lifecycleRepository($queueStored, $queueEvents, $now);
$commands = [];
$main = new RemoteSupportMain(
    $queueRepository,
    static function (string $command, string $queuedSessionId) use (&$commands): void {
        $commands[] = [$command, $queuedSessionId];
    },
    static fn(): string => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
);
$queuedStart = $main->requestStart();
$sameQueuedStart = $main->requestStart();
contractAssertSame($queuedStart->session_id, $sameQueuedStart->session_id, 'requestStart is idempotent');
contractAssertSame(1, count($commands), 'only one start command is queued');
$main->requestStop();
$main->requestStop();
contractAssertSame(2, count($commands), 'only one stop command is queued');

echo "Worker lifecycle contract: OK\n";
