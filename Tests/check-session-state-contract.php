<?php

declare(strict_types=1);

use Modules\ModuleRemoteSupport\Lib\SessionRepository;
use Modules\ModuleRemoteSupport\Lib\SessionStatus;
use Modules\ModuleRemoteSupport\Models\RemoteSupportSession;

require_once __DIR__ . '/bootstrap.php';

$idProperty = new ReflectionProperty(RemoteSupportSession::class, 'id');
$idDocComment = $idProperty->getDocComment();
contractAssert(is_string($idDocComment), 'session ID metadata is readable');
contractAssert(
    str_contains($idDocComment, '@Primary')
    && str_contains($idDocComment, '@Identity')
    && str_contains($idDocComment, '@Column(type="integer", nullable=false)'),
    'session ID exposes identity primary integer column metadata to Phalcon',
);

$stored = new RemoteSupportSession();
$stored->id = 1;
$stored->status = SessionStatus::OFF->value;

$load = static function () use (&$stored): RemoteSupportSession {
    return clone $stored;
};
$save = static function (RemoteSupportSession $session) use (&$stored): void {
    $stored = clone $session;
};
$transaction = static function (callable $callback): mixed {
    return $callback();
};
$clock = static fn(): int => 1_721_721_600;

$repository = new SessionRepository($load, $save, $transaction, $clock);
$sessionId = '0123456789abcdef0123456789abcdef';

$starting = $repository->beginStart($sessionId);
contractAssertSame(SessionStatus::STARTING->value, $starting->status, 'off + start');
contractAssertSame($sessionId, $starting->session_id, 'start stores session id');

$sameStarting = $repository->beginStart('ffffffffffffffffffffffffffffffff');
contractAssertSame($sessionId, $sameStarting->session_id, 'starting + start is idempotent');

$active = $repository->markActive(
    code: 'ABC-123',
    slot: 0,
    tunnelPort: 22_000,
    startedAt: 1_721_721_600,
    expiresAt: 1_721_750_400,
);
contractAssertSame(SessionStatus::ACTIVE->value, $active->status, 'starting + active');
contractAssertSame('0', $active->slot, 'server slot zero is persisted');

$sameActive = $repository->beginStart('eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee');
contractAssertSame($sessionId, $sameActive->session_id, 'active + start is idempotent');

$stopping = $repository->beginStop();
contractAssertSame(SessionStatus::STOPPING->value, $stopping->status, 'active + stop');
contractAssertSame(
    SessionStatus::STOPPING->value,
    $repository->beginStop()->status,
    'stopping + stop is idempotent',
);

$repository->markError('tunnel_disconnected');
$restarting = $repository->beginStart('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
contractAssertSame(SessionStatus::STARTING->value, $restarting->status, 'error + start');
contractAssertSame(
    'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    $restarting->session_id,
    'error + start replaces session id',
);

$repository->markActive(
    code: 'DEF-456',
    slot: 7,
    tunnelPort: 22_007,
    startedAt: 1_721_721_700,
    expiresAt: 1_721_750_500,
);
$reset = $repository->resetToOff();

contractAssertSame(SessionStatus::OFF->value, $reset->status, 'reset returns off');
foreach (['session_id', 'code', 'slot', 'tunnel_port', 'started_at', 'expires_at', 'error_code'] as $field) {
    contractAssertSame('', $reset->{$field}, sprintf('%s must be cleared', $field));
}
contractAssertSame('1', (string)$reset->id, 'repository always uses row ID 1');
contractAssert(!property_exists($reset, 'private_key'), 'model must not have a private-key field');
contractAssert(!property_exists($reset, 'private_key_path'), 'model must not have a private-key path');

echo "Session state contract: OK\n";
