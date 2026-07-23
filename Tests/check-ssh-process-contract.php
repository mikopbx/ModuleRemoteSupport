<?php

declare(strict_types=1);

use Modules\ModuleRemoteSupport\Lib\LobbyAllocation;
use Modules\ModuleRemoteSupport\Lib\ProcessHandle;
use Modules\ModuleRemoteSupport\Lib\RemoteSupportConfig;
use Modules\ModuleRemoteSupport\Lib\SshProcess;

require_once __DIR__ . '/bootstrap.php';

$calls = [];
$allocationOutput = "MIKO-LOBBY: v1\nCODE: ABC-123\n";
$run = static function (array $arguments) use (&$calls, $allocationOutput): array {
    $calls[] = ['mode' => 'run', 'arguments' => $arguments];

    return [
        'exitCode' => 0,
        'stdout' => $allocationOutput,
        'stderr' => '',
    ];
};
$spawn = static function (array $arguments) use (&$calls): ProcessHandle {
    $calls[] = ['mode' => 'spawn', 'arguments' => $arguments];

    return new class () extends ProcessHandle {
        public function __construct()
        {
        }
    };
};

$ssh = new SshProcess($run, $spawn);
$privateKey = '/run/mikopbx/remote-support/session/id_ed25519';
$knownHosts = '/run/mikopbx/remote-support/session/known_hosts';

contractAssertSame(
    $allocationOutput,
    $ssh->allocate($privateKey, $knownHosts),
    'allocation returns bounded stdout',
);

$allocation = new LobbyAllocation(
    code: 'ABC-123',
    slot: 42,
    tunnelPort: 22_042,
    tunnelUser: 'lobbytun',
    expiresAt: new DateTimeImmutable('2026-07-23T12:00:00+00:00'),
);
$handle = $ssh->startTunnel($allocation, $privateKey, $knownHosts);
contractAssert($handle instanceof ProcessHandle, 'tunnel returns a process handle');

$allocateArguments = $calls[0]['arguments'];
$tunnelArguments = $calls[1]['arguments'];

foreach ([$allocateArguments, $tunnelArguments] as $arguments) {
    contractAssert(is_array($arguments), 'process invocation must use an argv array');
    contractAssertSame('ssh', $arguments[0] ?? null, 'only ssh is invoked');
    contractAssert(!in_array('/bin/sh', $arguments, true), 'argv must not invoke /bin/sh');
    contractAssert(!in_array('/bin/bash', $arguments, true), 'argv must not invoke /bin/bash');
    contractAssert(!in_array('-c', $arguments, true), 'argv must not invoke a shell command');
    contractAssert(in_array('StrictHostKeyChecking=yes', $arguments, true), 'strict host-key checking');
    contractAssert(
        in_array('UserKnownHostsFile=' . $knownHosts, $arguments, true),
        'pinned known-hosts file',
    );
    contractAssert(in_array('BatchMode=yes', $arguments, true), 'batch mode');
    contractAssert(in_array('ConnectTimeout=10', $arguments, true), 'connection timeout');
    contractAssert(in_array('ServerAliveInterval=15', $arguments, true), 'keepalive interval');
    contractAssert(in_array('ServerAliveCountMax=2', $arguments, true), 'keepalive limit');
    contractAssert(
        in_array((string)RemoteSupportConfig::SUPPORT_PORT, $arguments, true),
        'support port is pinned',
    );
}

contractAssert(
    in_array('lobbyalloc@' . RemoteSupportConfig::SUPPORT_HOST, $allocateArguments, true),
    'allocation user and host are pinned',
);
contractAssert(
    in_array('ExitOnForwardFailure=yes', $tunnelArguments, true),
    'reverse bind failure is fatal',
);
contractAssert(
    in_array('127.0.0.1:22042:127.0.0.1:22', $tunnelArguments, true),
    'reverse forwarding uses the exact permitted loopback listener',
);
contractAssert(
    in_array('lobbytun@' . RemoteSupportConfig::SUPPORT_HOST, $tunnelArguments, true),
    'tunnel user and host are validated and pinned',
);

echo "SSH process contract: OK\n";
