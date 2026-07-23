<?php

declare(strict_types=1);

use Modules\ModuleRemoteSupport\Lib\AuthorizedKeysManagerInterface;
use Modules\ModuleRemoteSupport\Lib\ModuleLifecycleManager;
use Modules\ModuleRemoteSupport\Lib\RemoteSupportMain;
use Modules\ModuleRemoteSupport\Lib\RemoteSupportRuntimeInterface;
use Modules\ModuleRemoteSupport\Lib\SessionRepository;
use Modules\ModuleRemoteSupport\Lib\SessionStatus;
use Modules\ModuleRemoteSupport\Models\RemoteSupportSession;

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$setupPath = $root . '/Setup/PbxExtensionSetup.php';
$confPath = $root . '/Lib/RemoteSupportConf.php';
contractAssert(is_file($setupPath), 'module setup exists');
contractAssert(is_file($confPath), 'module configuration exists');

$setupSource = file_get_contents($setupPath);
$confSource = file_get_contents($confPath);
contractAssert(is_string($setupSource) && is_string($confSource), 'lifecycle sources are readable');
contractAssert(str_contains($setupSource, 'parent::installDB()'), 'install creates model table');
contractAssert(
    str_contains($setupSource, 'AdditionalMenuItemModuleRemoteSupport'),
    'install creates the exact sidebar entry',
);
contractAssert(str_contains($setupSource, 'findFirstByKey'), 'sidebar installation is idempotent');
contractAssert(str_contains($setupSource, 'unInstallDB'), 'uninstall cleanup is implemented');

foreach (
    [
        'onBeforeModuleEnable',
        'onAfterModuleEnable',
        'onBeforeModuleDisable',
        'onAfterModuleDisable',
        'onAfterPbxStarted',
        'getModuleWorkers',
    ] as $hook
) {
    contractAssert(str_contains($confSource, 'function ' . $hook), $hook . ' hook exists');
}

final class LifecycleRuntime implements RemoteSupportRuntimeInterface
{
    /** @var list<string> */
    public array $events = [];

    public function preflight(): void
    {
        $this->events[] = 'preflight';
    }

    public function create(): void
    {
    }

    public function generateKeyPair(): void
    {
    }

    public function writeKnownHosts(): void
    {
    }

    public function privateKeyPath(): string
    {
        return '/runtime/id_ed25519';
    }

    public function knownHostsPath(): string
    {
        return '/runtime/known_hosts';
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

final class LifecycleKeys implements AuthorizedKeysManagerInterface
{
    /** @var list<string> */
    public array $events = [];

    public bool $failRemoval = false;

    public function install(string $sessionId): void
    {
    }

    public function removeManagedKeys(): void
    {
        $this->events[] = 'keys:remove';
        if ($this->failRemoval) {
            throw new RuntimeException('revocation failed');
        }
    }
}

/**
 * @param list<string> $events
 */
function moduleLifecycleRepository(
    RemoteSupportSession &$stored,
    array &$events,
): SessionRepository {
    return new SessionRepository(
        static function () use (&$stored): RemoteSupportSession {
            return clone $stored;
        },
        static function (RemoteSupportSession $session) use (&$stored, &$events): void {
            $stored = clone $session;
            $events[] = 'state:' . $session->status;
        },
        static fn(callable $callback): mixed => $callback(),
        static fn(): int => 1_721_721_600,
    );
}

$stored = new RemoteSupportSession();
$stored->id = 1;
$stored->status = SessionStatus::ACTIVE->value;
$stored->session_id = '0123456789abcdef0123456789abcdef';
$events = [];
$repository = moduleLifecycleRepository($stored, $events);
$runtime = new LifecycleRuntime();
$keys = new LifecycleKeys();
$main = new RemoteSupportMain(
    $repository,
    static function (string $command, string $sessionId) use (&$events): void {
        $events[] = 'publish:' . $command . ':' . $sessionId;
    },
);
$sleep = static function () use (&$stored, &$events): void {
    $events[] = 'wait';
    $stored->status = SessionStatus::OFF->value;
};
$manager = new ModuleLifecycleManager(
    $repository,
    $main,
    $runtime,
    $keys,
    $sleep,
);

$manager->preflight();
contractAssertSame(['preflight'], $runtime->events, 'enable runs capability preflight');

$manager->disableAndRevoke();
contractAssert(
    in_array('publish:stop:0123456789abcdef0123456789abcdef', $events, true),
    'disable requests worker stop',
);
contractAssertSame(
    ['keys:remove'],
    $keys->events,
    'disable revokes managed access before reporting success',
);
contractAssertSame(
    ['preflight', 'runtime:cleanup-stale'],
    $runtime->events,
    'disable removes stale runtime',
);
contractAssertSame(SessionStatus::OFF->value, $stored->status, 'disable ends in off state');

$events = [];
$runtime->events = [];
$keys->events = [];
$stored->status = SessionStatus::ACTIVE->value;
$stored->session_id = 'fedcba9876543210fedcba9876543210';
$manager->recoverAfterPbxStart();
contractAssertSame(['keys:remove'], $keys->events, 'PBX startup removes stale module keys');
contractAssertSame(['runtime:cleanup-stale'], $runtime->events, 'PBX startup removes stale runtime');
contractAssertSame(SessionStatus::OFF->value, $stored->status, 'PBX startup resets state');
contractAssert(
    !array_filter(
        $events,
        static fn(string $event): bool => str_starts_with($event, 'publish:start'),
    ),
    'PBX startup never reconnects',
);

$keys->failRemoval = true;
$failedClosed = false;
try {
    $manager->recoverAfterPbxStart();
} catch (RuntimeException) {
    $failedClosed = true;
}
contractAssert($failedClosed, 'revocation failure is visible and fails closed');

echo "Module lifecycle contract: OK\n";
