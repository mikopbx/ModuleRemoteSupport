<?php

declare(strict_types=1);

use Modules\ModuleRemoteSupport\Lib\LobbyAllocation;
use Modules\ModuleRemoteSupport\Lib\ProcessHandle;
use Modules\ModuleRemoteSupport\Lib\RemoteSupportConfig;
use Modules\ModuleRemoteSupport\Lib\SshProcess;
use Modules\ModuleRemoteSupport\Lib\WebForwardTarget;

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
contractAssertSame(
    1,
    count(array_keys($tunnelArguments, '-R', true)),
    'v1 allocation opens exactly one reverse forward',
);
contractAssert(
    in_array('lobbytun@' . RemoteSupportConfig::SUPPORT_HOST, $tunnelArguments, true),
    'tunnel user and host are validated and pinned',
);

$allocationV2 = new LobbyAllocation(
    code: 'ABC-123',
    slot: 42,
    tunnelPort: 22_042,
    tunnelUser: 'lobbytun',
    expiresAt: new DateTimeImmutable('2026-07-23T12:00:00+00:00'),
    webTunnelPort: 23_042,
);
$webTarget = new WebForwardTarget('192.0.2.10', 443);

$ssh->startTunnel($allocationV2, $privateKey, $knownHosts, $webTarget);
$webTunnelArguments = $calls[2]['arguments'];
contractAssertSame(
    2,
    count(array_keys($webTunnelArguments, '-R', true)),
    'v2 allocation opens both reverse forwards',
);
contractAssert(
    in_array('127.0.0.1:22042:127.0.0.1:22', $webTunnelArguments, true),
    'v2 keeps the SSH loopback forward',
);
contractAssert(
    in_array('127.0.0.1:23042:192.0.2.10:443', $webTunnelArguments, true),
    'web forward binds loopback on the box and targets the real station address',
);
contractAssert(
    !in_array('127.0.0.1:23042:127.0.0.1:443', $webTunnelArguments, true),
    'web forward never targets the station loopback bypass',
);
contractAssert(
    in_array('ExitOnForwardFailure=yes', $webTunnelArguments, true),
    'web reverse bind failure stays fatal',
);

$ssh->startTunnel($allocationV2, $privateKey, $knownHosts, null);
$degradedArguments = $calls[3]['arguments'];
contractAssertSame(
    1,
    count(array_keys($degradedArguments, '-R', true)),
    'v2 without a resolvable station address degrades to SSH only',
);

$ssh->startTunnel($allocation, $privateKey, $knownHosts, $webTarget);
$v1TargetArguments = $calls[4]['arguments'];
contractAssertSame(
    1,
    count(array_keys($v1TargetArguments, '-R', true)),
    'v1 allocation ignores a provided web target',
);

$invalidTargets = [
    'loopback address' => ['127.0.0.1', 443],
    'unspecified address' => ['0.0.0.0', 443],
    'hostname instead of ip' => ['station.local', 443],
    'ipv6 address' => ['2001:db8::1', 443],
    'shell metacharacters' => ['192.0.2.10;rm', 443],
    'zero port' => ['192.0.2.10', 0],
    'port above tcp range' => ['192.0.2.10', 65_536],
];
foreach ($invalidTargets as $case => [$ip, $port]) {
    $rejected = false;
    try {
        new WebForwardTarget($ip, $port);
    } catch (RuntimeException) {
        $rejected = true;
    }

    contractAssert($rejected, $case . ' must be rejected');
}

$outOfRangeWebPort = new LobbyAllocation(
    code: 'ABC-123',
    slot: 42,
    tunnelPort: 22_042,
    tunnelUser: 'lobbytun',
    expiresAt: new DateTimeImmutable('2026-07-23T12:00:00+00:00'),
    webTunnelPort: 24_500,
);
$rejected = false;
try {
    $ssh->startTunnel($outOfRangeWebPort, $privateKey, $knownHosts, $webTarget);
} catch (RuntimeException) {
    $rejected = true;
}
contractAssert($rejected, 'web tunnel port outside the lobby range must be rejected');

echo "SSH process contract: OK\n";
