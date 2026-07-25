<?php

declare(strict_types=1);

use Modules\ModuleRemoteSupport\Lib\RestAPI\Session\Actions\GetStatusAction;
use Modules\ModuleRemoteSupport\Lib\RestAPI\Session\Actions\StartAction;
use Modules\ModuleRemoteSupport\Lib\RestAPI\Session\Actions\StopAction;
use Modules\ModuleRemoteSupport\Lib\SessionStatus;
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
$session->web_login = 'miko-support-0a1b2c3d4e5f6a7b';
$session->web_password_hash = '$2y$10$abcdefghijklmnopqrstuv';

$statusResult = GetStatusAction::fromSession($session, 'abcde-fghjk-mnpqr-stuvw');
contractAssertSame(true, $statusResult->success, 'GET status uses successful MikoPBX envelope');
contractAssertSame('active', $statusResult->data['state'] ?? null, 'GET normalizes state');
contractAssertSame('ABC-123', $statusResult->data['code'] ?? null, 'GET returns active code');
contractAssertSame(
    'miko-support-0a1b2c3d4e5f6a7b',
    $statusResult->data['webLogin'] ?? null,
    'GET returns the ephemeral web login while active',
);
contractAssertSame(
    'abcde-fghjk-mnpqr-stuvw',
    $statusResult->data['webPassword'] ?? null,
    'GET returns the runtime web password while active',
);
contractAssert(
    !array_key_exists('contacts', $statusResult->data),
    'GET does not expose dynamic contacts',
);
contractAssert(
    !array_key_exists('supportSite', $statusResult->data),
    'GET does not expose a website fallback',
);

$offSession = new RemoteSupportSession();
$offSession->id = 1;
$offSession->status = SessionStatus::OFF->value;
$offResult = GetStatusAction::fromSession($offSession, 'abcde-fghjk-mnpqr-stuvw');
contractAssertSame('', $offResult->data['webLogin'] ?? null, 'off state exposes no web login');
contractAssertSame(
    '',
    $offResult->data['webPassword'] ?? null,
    'off state never leaks a runtime web password',
);

$dataStructure = file_get_contents(
    $root . '/Lib/RestAPI/Session/DataStructure.php',
);
contractAssert(is_string($dataStructure), 'REST data structure must be readable');
contractAssert(
    !str_contains($dataStructure, "'contacts' =>"),
    'REST schema has no contacts field',
);
contractAssert(
    !str_contains($dataStructure, "'supportSite' =>"),
    'REST schema has no supportSite field',
);
contractAssert(
    str_contains($dataStructure, "'webLogin' =>") && str_contains($dataStructure, "'webPassword' =>"),
    'REST schema documents the ephemeral web credential fields',
);

$serialized = json_encode($statusResult->data, JSON_THROW_ON_ERROR);
foreach (
    [
        'private_key',
        'privateKey',
        'raw_ssh',
        'process_id',
        'pid',
        'tunnel_port',
        'slot',
        'web_password_hash',
        'passwordHash',
        '$2y$10$',
    ] as $forbidden
) {
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
