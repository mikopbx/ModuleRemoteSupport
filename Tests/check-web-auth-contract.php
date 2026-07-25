<?php

declare(strict_types=1);

use Modules\ModuleRemoteSupport\Lib\RemoteSupportConfig;
use Modules\ModuleRemoteSupport\Lib\SessionRepository;
use Modules\ModuleRemoteSupport\Lib\SessionStatus;
use Modules\ModuleRemoteSupport\Lib\WebAccessAuthenticator;
use Modules\ModuleRemoteSupport\Lib\WebCredentialGenerator;
use Modules\ModuleRemoteSupport\Models\RemoteSupportSession;

require_once __DIR__ . '/bootstrap.php';

$hashCalls = [];
$hash = static function (string $password) use (&$hashCalls): string {
    $hashCalls[] = $password;

    return '$test$' . strrev($password);
};
$adminLogin = static fn(): string => 'admin';

$generator = new WebCredentialGenerator($hash, $adminLogin);
$credential = $generator->generate();

contractAssert(
    preg_match('/\Amiko-support-[a-z0-9]{16}\z/D', $credential->login) === 1,
    'generated login carries the module prefix and random suffix',
);
contractAssert(
    str_starts_with($credential->login, RemoteSupportConfig::WEB_LOGIN_PREFIX),
    'generated login uses the shared prefix constant',
);
contractAssert(
    preg_match(
        '/\A[a-hj-km-np-z2-9]{5}(?:-[a-hj-km-np-z2-9]{5}){3}\z/D',
        $credential->password,
    ) === 1,
    'password is four dictation-safe groups without ambiguous characters',
);
contractAssertSame(
    '$test$' . strrev($credential->password),
    $credential->passwordHash,
    'password hash is produced by the injected core hash function',
);
contractAssertSame(
    [$credential->password],
    $hashCalls,
    'only the password is ever hashed',
);

$second = $generator->generate();
contractAssert(
    $credential->login !== $second->login && $credential->password !== $second->password,
    'credentials are random per generation',
);

$collidingAdmin = new WebCredentialGenerator(
    $hash,
    static fn(): string => 'MIKO-SUPPORT-COLLIDINGLOGIN00',
    static fn(): string => 'miko-support-collidinglogin00',
);
$rejected = false;
try {
    $collidingAdmin->generate();
} catch (RuntimeException) {
    $rejected = true;
}
contractAssert($rejected, 'login equal to the station admin login must never be issued');

function webAuthRepository(RemoteSupportSession &$stored): SessionRepository
{
    return new SessionRepository(
        static function () use (&$stored): RemoteSupportSession {
            return clone $stored;
        },
        static function (RemoteSupportSession $session) use (&$stored): void {
            $stored = clone $session;
        },
        static fn(callable $callback): mixed => $callback(),
        static fn(): int => 1_721_721_600,
    );
}

function webAuthSession(string $status): RemoteSupportSession
{
    $session = new RemoteSupportSession();
    $session->id = 1;
    $session->status = $status;
    $session->session_id = '0123456789abcdef0123456789abcdef';
    $session->web_login = 'miko-support-0a1b2c3d4e5f6a7b';
    $session->web_password_hash = '$test$drowssap-terces';

    return $session;
}

$verify = static fn(string $password, string $hash): bool => ('$test$' . strrev($password)) === $hash;
$stored = webAuthSession(SessionStatus::ACTIVE->value);
$authenticator = new WebAccessAuthenticator(
    webAuthRepository($stored),
    $verify,
    static fn(): string => 'admin',
);

contractAssertSame(
    [
        'role' => 'admins',
        'homePage' => '/admin-cabinet/extensions/index',
        'userName' => 'miko-support-0a1b2c3d4e5f6a7b',
    ],
    $authenticator->authenticate('miko-support-0a1b2c3d4e5f6a7b', 'secret-password'),
    'active session with a valid ephemeral credential yields the full admin role',
);

contractAssertSame(
    [],
    $authenticator->authenticate('miko-support-0a1b2c3d4e5f6a7b', 'wrong-password'),
    'wrong password is rejected',
);
contractAssertSame(
    [],
    $authenticator->authenticate('miko-support-ffffffffffffffff', 'secret-password'),
    'unknown login is rejected',
);
contractAssertSame(
    [],
    $authenticator->authenticate('', 'secret-password'),
    'empty login is rejected',
);
contractAssertSame(
    [],
    $authenticator->authenticate('miko-support-0a1b2c3d4e5f6a7b', ''),
    'empty password is rejected',
);

foreach (
    [
        SessionStatus::OFF,
        SessionStatus::STARTING,
        SessionStatus::STOPPING,
        SessionStatus::ERROR,
    ] as $inactiveStatus
) {
    $inactiveStored = webAuthSession($inactiveStatus->value);
    $inactiveAuthenticator = new WebAccessAuthenticator(
        webAuthRepository($inactiveStored),
        $verify,
        static fn(): string => 'admin',
    );
    contractAssertSame(
        [],
        $inactiveAuthenticator->authenticate('miko-support-0a1b2c3d4e5f6a7b', 'secret-password'),
        $inactiveStatus->value . ' session rejects web authentication',
    );
}

$noCredentialStored = webAuthSession(SessionStatus::ACTIVE->value);
$noCredentialStored->web_login = '';
$noCredentialStored->web_password_hash = '';
$noCredentialAuthenticator = new WebAccessAuthenticator(
    webAuthRepository($noCredentialStored),
    $verify,
    static fn(): string => 'admin',
);
contractAssertSame(
    [],
    $noCredentialAuthenticator->authenticate('miko-support-0a1b2c3d4e5f6a7b', 'secret-password'),
    'active SSH-only session without a web credential rejects web authentication',
);

$adminCollisionStored = webAuthSession(SessionStatus::ACTIVE->value);
$adminCollisionAuthenticator = new WebAccessAuthenticator(
    webAuthRepository($adminCollisionStored),
    $verify,
    static fn(): string => 'MIKO-SUPPORT-0A1B2C3D4E5F6A7B',
);
contractAssertSame(
    [],
    $adminCollisionAuthenticator->authenticate('miko-support-0a1b2c3d4e5f6a7b', 'secret-password'),
    'login matching the station admin login is rejected even with a valid password',
);

$failingAuthenticator = new WebAccessAuthenticator(
    new SessionRepository(
        static function (): RemoteSupportSession {
            throw new RuntimeException('database unavailable');
        },
        static function (RemoteSupportSession $session): void {
        },
        static fn(callable $callback): mixed => $callback(),
        static fn(): int => 1_721_721_600,
    ),
    $verify,
    static fn(): string => 'admin',
);
contractAssertSame(
    [],
    $failingAuthenticator->authenticate('miko-support-0a1b2c3d4e5f6a7b', 'secret-password'),
    'storage failure fails closed without leaking an exception',
);

$confSource = file_get_contents(dirname(__DIR__) . '/Lib/RemoteSupportConf.php');
contractAssert(is_string($confSource), 'module configuration is readable');
contractAssert(
    str_contains($confSource, 'function authenticateUser'),
    'ConfigClass hook authenticateUser is implemented',
);
contractAssert(
    str_contains($confSource, 'WebAccessAuthenticator'),
    'authenticateUser delegates to the web access authenticator',
);

echo "Web auth contract: OK\n";
