<?php

declare(strict_types=1);

use Modules\ModuleRemoteSupport\Lib\LobbyProtocol;

require_once __DIR__ . '/bootstrap.php';

$clock = static fn(): int => 1_721_721_600;
$protocol = new LobbyProtocol($clock);
$valid = implode(
    "\n",
    [
        'MIKO-LOBBY: v1',
        'CODE: ABC-123',
        'SLOT: 42',
        'TUNNEL_PORT: 22042',
        'TUNNEL_USER: lobbytun',
        'EXPIRES: 2026-07-23T12:00:00+00:00',
    ],
) . "\n";

$allocation = $protocol->parse($valid);
contractAssertSame('ABC-123', $allocation->code, 'code is parsed');
contractAssertSame(42, $allocation->slot, 'slot is parsed');
contractAssertSame(22_042, $allocation->tunnelPort, 'tunnel port is parsed');
contractAssertSame('lobbytun', $allocation->tunnelUser, 'tunnel user is pinned');
contractAssertSame(null, $allocation->webTunnelPort, 'v1 has no web tunnel port');
contractAssert(
    $allocation->expiresAt->getTimestamp() > $clock(),
    'allocation must expire in the future',
);

$validV2 = implode(
    "\n",
    [
        'MIKO-LOBBY: v2',
        'CODE: ABC-123',
        'SLOT: 42',
        'TUNNEL_PORT: 22042',
        'WEB_TUNNEL_PORT: 23042',
        'TUNNEL_USER: lobbytun',
        'EXPIRES: 2026-07-23T12:00:00+00:00',
    ],
) . "\n";

$allocationV2 = $protocol->parse($validV2);
contractAssertSame('ABC-123', $allocationV2->code, 'v2 code is parsed');
contractAssertSame(42, $allocationV2->slot, 'v2 slot is parsed');
contractAssertSame(22_042, $allocationV2->tunnelPort, 'v2 tunnel port is parsed');
contractAssertSame(23_042, $allocationV2->webTunnelPort, 'v2 web tunnel port is parsed');
contractAssertSame('lobbytun', $allocationV2->tunnelUser, 'v2 tunnel user is pinned');

$zeroSlotV2 = $protocol->parse(
    str_replace(
        ['SLOT: 42', 'TUNNEL_PORT: 22042', 'WEB_TUNNEL_PORT: 23042'],
        ['SLOT: 0', 'TUNNEL_PORT: 22000', 'WEB_TUNNEL_PORT: 23000'],
        $validV2,
    ),
);
contractAssertSame(23_000, $zeroSlotV2->webTunnelPort, 'v2 slot zero web port is valid');

$zeroSlotAllocation = $protocol->parse(
    str_replace(
        ['SLOT: 42', 'TUNNEL_PORT: 22042'],
        ['SLOT: 0', 'TUNNEL_PORT: 22000'],
        $valid,
    ),
);
contractAssertSame(0, $zeroSlotAllocation->slot, 'server slot zero is valid');
contractAssertSame(22_000, $zeroSlotAllocation->tunnelPort, 'slot zero port is valid');

$invalidResponses = [
    'missing field' => str_replace("SLOT: 42\n", '', $valid),
    'duplicate field' => str_replace("SLOT: 42\n", "SLOT: 42\nSLOT: 42\n", $valid),
    'wrong field' => str_replace('CODE:', 'SESSION_CODE:', $valid),
    'control byte' => str_replace('ABC-123', "ABC-\x00123", $valid),
    'oversized output' => str_repeat('A', 8_193),
    'lowercase code' => str_replace('ABC-123', 'abc-123', $valid),
    'malformed code' => str_replace('ABC-123', 'ABCD-123', $valid),
    'non-numeric slot' => str_replace('SLOT: 42', 'SLOT: XX', $valid),
    'slot out of range' => str_replace('SLOT: 42', 'SLOT: 1000', $valid),
    'non-numeric port' => str_replace('TUNNEL_PORT: 22042', 'TUNNEL_PORT: abc', $valid),
    'port outside lobby range' => str_replace('TUNNEL_PORT: 22042', 'TUNNEL_PORT: 65535', $valid),
    'unexpected user' => str_replace('lobbytun', 'root', $valid),
    'invalid timestamp' => str_replace(
        '2026-07-23T12:00:00+00:00',
        'tomorrow',
        $valid,
    ),
    'expired timestamp' => str_replace(
        '2026-07-23T12:00:00+00:00',
        '2020-01-01T00:00:00+00:00',
        $valid,
    ),
    'trailing command' => $valid . "rm -rf /tmp/example\n",
    'unknown protocol version' => str_replace('MIKO-LOBBY: v1', 'MIKO-LOBBY: v3', $valid),
    'v1 with web port' => str_replace(
        "TUNNEL_PORT: 22042\n",
        "TUNNEL_PORT: 22042\nWEB_TUNNEL_PORT: 23042\n",
        $valid,
    ),
    'v2 without web port' => str_replace("WEB_TUNNEL_PORT: 23042\n", '', $validV2),
    'v2 duplicate web port' => str_replace(
        "WEB_TUNNEL_PORT: 23042\n",
        "WEB_TUNNEL_PORT: 23042\nWEB_TUNNEL_PORT: 23042\n",
        $validV2,
    ),
    'v2 non-numeric web port' => str_replace(
        'WEB_TUNNEL_PORT: 23042',
        'WEB_TUNNEL_PORT: abc',
        $validV2,
    ),
    'v2 web port below range' => str_replace(
        'WEB_TUNNEL_PORT: 23042',
        'WEB_TUNNEL_PORT: 22999',
        $validV2,
    ),
    'v2 web port above range' => str_replace(
        'WEB_TUNNEL_PORT: 23042',
        'WEB_TUNNEL_PORT: 24000',
        $validV2,
    ),
    'v2 web port outside tcp range' => str_replace(
        'WEB_TUNNEL_PORT: 23042',
        'WEB_TUNNEL_PORT: 65536',
        $validV2,
    ),
    'v2 web port out of field order' => str_replace(
        ["TUNNEL_PORT: 22042\nWEB_TUNNEL_PORT: 23042\n", "TUNNEL_USER: lobbytun\n"],
        ["TUNNEL_PORT: 22042\n", "TUNNEL_USER: lobbytun\nWEB_TUNNEL_PORT: 23042\n"],
        $validV2,
    ),
];

foreach ($invalidResponses as $case => $response) {
    $rejected = false;
    try {
        $protocol->parse($response);
    } catch (DomainException) {
        $rejected = true;
    }

    contractAssert($rejected, $case . ' must be rejected');
}

echo "Lobby protocol contract: OK\n";
