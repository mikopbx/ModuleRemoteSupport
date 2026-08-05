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
use Modules\ModuleRemoteSupport\Lib\StationSshUser;
use Modules\ModuleRemoteSupport\Lib\WebCredentialGenerator;
use Modules\ModuleRemoteSupport\Lib\WebForwardTarget;
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

    /** @var null|array{login: string, password: string} */
    public ?array $webCredential = null;

    public bool $failWebCredential = false;

    public function writeWebCredential(string $login, string $password): void
    {
        $this->events[] = 'runtime:web-credential';
        if ($this->failWebCredential) {
            throw new RuntimeException('credential write failed');
        }
        $this->webCredential = ['login' => $login, 'password' => $password];
    }

    public function readWebCredential(): ?array
    {
        return $this->webCredential;
    }

    public function cleanup(): void
    {
        $this->events[] = 'runtime:cleanup';
        $this->webCredential = null;
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
    public bool $allocateV2 = false;
    public ?FakeTunnelHandle $handle = null;
    public ?WebForwardTarget $lastWebTarget = null;
    public string $lastStationUser = '';

    /**
     * @param list<string> $events
     */
    public function __construct(array &$events)
    {
        $this->events =& $events;
    }

    public function allocate(
        string $privateKey,
        string $knownHosts,
        StationSshUser $stationUser,
    ): string {
        $this->events[] = 'ssh:allocate';
        $this->lastStationUser = $stationUser->login;
        if ($this->failAllocation) {
            throw new RuntimeException('allocation failed');
        }

        if ($this->allocateV2) {
            return "MIKO-LOBBY: v2\n"
                . "CODE: ABC-123\n"
                . "SLOT: 42\n"
                . "TUNNEL_PORT: 22042\n"
                . "WEB_TUNNEL_PORT: 23042\n"
                . "TUNNEL_USER: lobbytun\n"
                . "EXPIRES: 2026-07-23T12:00:00+00:00\n";
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
        ?WebForwardTarget $webTarget = null,
    ): ProcessHandle {
        $this->events[] = $webTarget === null ? 'ssh:start-tunnel' : 'ssh:start-tunnel-web';
        $this->lastWebTarget = $webTarget;
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

$testWebCredentials = new WebCredentialGenerator(
    static fn(string $password): string => '$test$' . strrev($password),
    static fn(): string => 'admin',
);

/**
 * @param list<string> $events
 */
function webLifecycle(
    RemoteSupportSession &$stored,
    array &$events,
    int &$now,
    FakeRemoteSupportRuntime $runtime,
    FakeSshProcess $ssh,
    ?WebForwardTarget $resolvedTarget,
    WebCredentialGenerator $credentials,
): RemoteSupportTunnelLifecycle {
    return new RemoteSupportTunnelLifecycle(
        lifecycleRepository($stored, $events, $now),
        $runtime,
        new FakeAuthorizedKeysManager($events),
        $ssh,
        static function () use (&$now): int {
            return $now;
        },
        static fn(ProcessHandle $handle): bool => $handle->isRunning(),
        static fn(): ?WebForwardTarget => $resolvedTarget,
        $credentials,
        static fn(): StationSshUser => new StationSshUser('mikoadmin'),
    );
}

$webEvents = [];
$webStored = offSession();
$webRuntime = new FakeRemoteSupportRuntime($webEvents);
$webSsh = new FakeSshProcess($webEvents);
$webSsh->allocateV2 = true;
$webLifecycle = webLifecycle(
    $webStored,
    $webEvents,
    $now,
    $webRuntime,
    $webSsh,
    new WebForwardTarget('192.0.2.10', 443),
    $testWebCredentials,
);
$webActive = $webLifecycle->start($sessionId);
contractAssertSame(SessionStatus::ACTIVE->value, $webActive->status, 'v2 session becomes active');
contractAssertSame(
    'mikoadmin',
    $webSsh->lastStationUser,
    'the configured station account reaches the allocation request',
);
contractAssert(
    preg_match('/\Amiko-support-[a-z0-9]{16}\z/D', (string)$webActive->web_login) === 1,
    'v2 session stores a generated web login',
);
contractAssert(
    str_starts_with((string)$webActive->web_password_hash, '$test$'),
    'v2 session stores only the injected hash of the web password',
);
contractAssert(is_array($webRuntime->webCredential), 'plaintext credential lives in the runtime only');
contractAssertSame(
    $webActive->web_login,
    $webRuntime->webCredential['login'],
    'runtime credential login matches the session row',
);
contractAssertSame(
    '$test$' . strrev($webRuntime->webCredential['password']),
    $webActive->web_password_hash,
    'stored hash corresponds to the runtime plaintext password',
);
contractAssert(
    in_array('ssh:start-tunnel-web', $webEvents, true),
    'v2 session opens the tunnel with the web forward target',
);
contractAssertSame('192.0.2.10', $webSsh->lastWebTarget?->stationIp, 'web target is the resolved station IP');
contractAssert(
    array_search('runtime:web-credential', $webEvents, true)
        < array_search('ssh:start-tunnel-web', $webEvents, true),
    'credential exists before the web listener can appear',
);

$webLifecycle->stop();
contractAssertSame('', $webStored->web_login, 'stop clears the stored web login');
contractAssertSame('', $webStored->web_password_hash, 'stop clears the stored web password hash');
contractAssertSame(null, $webRuntime->webCredential, 'stop removes the plaintext credential');

$degradedEvents = [];
$degradedStored = offSession();
$degradedRuntime = new FakeRemoteSupportRuntime($degradedEvents);
$degradedSsh = new FakeSshProcess($degradedEvents);
$degradedSsh->allocateV2 = true;
$degradedLifecycle = webLifecycle(
    $degradedStored,
    $degradedEvents,
    $now,
    $degradedRuntime,
    $degradedSsh,
    null,
    $testWebCredentials,
);
$degraded = $degradedLifecycle->start($sessionId);
contractAssertSame(
    SessionStatus::ACTIVE->value,
    $degraded->status,
    'unresolvable station address degrades to an SSH-only session',
);
contractAssertSame('', $degraded->web_login, 'degraded session has no web login');
contractAssertSame(null, $degradedRuntime->webCredential, 'degraded session writes no credential');
contractAssert(
    in_array('ssh:start-tunnel', $degradedEvents, true)
        && !in_array('ssh:start-tunnel-web', $degradedEvents, true),
    'degraded session opens the SSH forward only',
);

$v1Events = [];
$v1Stored = offSession();
$v1Runtime = new FakeRemoteSupportRuntime($v1Events);
$v1Ssh = new FakeSshProcess($v1Events);
$v1Lifecycle = webLifecycle(
    $v1Stored,
    $v1Events,
    $now,
    $v1Runtime,
    $v1Ssh,
    new WebForwardTarget('192.0.2.10', 443),
    $testWebCredentials,
);
$v1Session = $v1Lifecycle->start($sessionId);
contractAssertSame(SessionStatus::ACTIVE->value, $v1Session->status, 'v1 server keeps working');
contractAssertSame('', $v1Session->web_login, 'v1 session issues no web credential');
contractAssertSame(null, $v1Runtime->webCredential, 'v1 session writes no credential file');
contractAssert(
    !in_array('ssh:start-tunnel-web', $v1Events, true),
    'v1 session never opens a web forward',
);

$credentialFailEvents = [];
$credentialFailStored = offSession();
$credentialFailRuntime = new FakeRemoteSupportRuntime($credentialFailEvents);
$credentialFailRuntime->failWebCredential = true;
$credentialFailSsh = new FakeSshProcess($credentialFailEvents);
$credentialFailSsh->allocateV2 = true;
$credentialFailLifecycle = webLifecycle(
    $credentialFailStored,
    $credentialFailEvents,
    $now,
    $credentialFailRuntime,
    $credentialFailSsh,
    new WebForwardTarget('192.0.2.10', 443),
    $testWebCredentials,
);
$credentialFail = $credentialFailLifecycle->start($sessionId);
contractAssertSame(
    SessionStatus::ERROR->value,
    $credentialFail->status,
    'web credential failure is a visible error',
);
contractAssertSame(
    'web_credential_failed',
    $credentialFail->error_code,
    'web credential failure uses a safe stable code',
);
contractAssert(
    !in_array('ssh:start-tunnel-web', $credentialFailEvents, true)
        && !in_array('ssh:start-tunnel', $credentialFailEvents, true),
    'no tunnel starts when the web credential cannot be stored',
);

echo "Worker lifecycle contract: OK\n";
