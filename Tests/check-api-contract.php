<?php

declare(strict_types=1);

use Modules\ModuleRemoteSupport\Lib\RestAPI\Session\Actions\GetStatusAction;
use Modules\ModuleRemoteSupport\Lib\RestAPI\Session\Actions\StartAction;
use Modules\ModuleRemoteSupport\Lib\RestAPI\Session\Actions\StopAction;
use Modules\ModuleRemoteSupport\Lib\SessionStatus;
use Modules\ModuleRemoteSupport\Lib\SupportContact;
use Modules\ModuleRemoteSupport\Models\RemoteSupportSession;

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$controllerPath = $root . '/Lib/RestAPI/Session/Controller.php';
contractAssert(is_file($controllerPath), 'REST controller must exist');
$controller = file_get_contents($controllerPath);
contractAssert(is_string($controller), 'REST controller must be readable');

contractAssert(
    str_contains($controller, "path: '/pbxcore/api/v3/module-remote-support/session'"),
    'ApiResource path is exact',
);
contractAssert(
    str_contains($controller, "'GET' => ['getStatus']")
        && str_contains($controller, "'POST' => ['start', 'stop']"),
    'HttpMapping exposes exact GET/start/stop operations',
);
contractAssert(
    str_contains($controller, "collectionLevelMethods: ['getStatus']")
        && str_contains($controller, "customMethods: ['start', 'stop']"),
    'GET is collection-level and mutations use colon custom methods',
);
contractAssert(
    str_contains(
        $controller,
        'requirements: [SecurityType::LOCALHOST, SecurityType::BEARER_TOKEN]',
    ),
    'resource requires localhost or bearer-token authentication',
);
contractAssert(!str_contains($controller, 'SecurityType::PUBLIC'), 'no public REST endpoint');
contractAssert(!str_contains($controller, 'ANONYMOUS'), 'no anonymous REST endpoint');

$session = new RemoteSupportSession();
$session->id = 1;
$session->status = SessionStatus::ACTIVE->value;
$session->session_id = '0123456789abcdef0123456789abcdef';
$session->code = 'ABC-123';
$session->slot = '42';
$session->tunnel_port = '22042';
$session->started_at = '1721721600';
$session->expires_at = '1721750400';
$session->error_code = '';

$contacts = [
    new SupportContact('phone', 'Phone', 'tel:+10000000000'),
    new SupportContact('telegram', 'Telegram', 'https://t.me/miko_support'),
];
$statusResult = GetStatusAction::fromSession($session, $contacts);
contractAssertSame(true, $statusResult->success, 'GET status uses successful MikoPBX envelope');
contractAssertSame('active', $statusResult->data['state'] ?? null, 'GET normalizes state');
contractAssertSame('ABC-123', $statusResult->data['code'] ?? null, 'GET returns active code');
contractAssertSame(2, count($statusResult->data['contacts'] ?? []), 'GET returns validated contacts');

$serialized = json_encode($statusResult->data, JSON_THROW_ON_ERROR);
foreach (['private_key', 'privateKey', 'raw_ssh', 'process_id', 'pid', 'tunnel_port', 'slot'] as $forbidden) {
    contractAssert(!str_contains($serialized, $forbidden), 'response excludes ' . $forbidden);
}

$startCalls = 0;
$startResult = StartAction::execute(
    static function () use (&$startCalls, $session): RemoteSupportSession {
        $startCalls++;

        return $session;
    },
);
contractAssertSame(1, $startCalls, 'start action delegates once');
contractAssertSame(true, $startResult->success, 'start returns standard success envelope');

$stopCalls = 0;
$stopResult = StopAction::execute(
    static function () use (&$stopCalls, $session): RemoteSupportSession {
        $stopCalls++;

        return $session;
    },
);
contractAssertSame(1, $stopCalls, 'stop action delegates once');
contractAssertSame(true, $stopResult->success, 'stop returns standard success envelope');

$errorResult = StartAction::execute(
    static function (): RemoteSupportSession {
        throw new RuntimeException('raw SSH output and /private/key/path');
    },
);
contractAssertSame(false, $errorResult->success, 'raw exception becomes API failure');
contractAssertSame(
    ['rest_error_internal'],
    $errorResult->messages['error'] ?? null,
    'errors expose only a localized safe code',
);
contractAssert(
    !str_contains(json_encode($errorResult->getResult(), JSON_THROW_ON_ERROR), 'raw SSH'),
    'raw exception text is never returned',
);

echo "REST API contract: OK\n";
