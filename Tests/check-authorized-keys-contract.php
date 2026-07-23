<?php

declare(strict_types=1);

use Modules\ModuleRemoteSupport\Lib\AuthorizedKeysManager;
use Modules\ModuleRemoteSupport\Lib\RemoteSupportConfig;

require_once __DIR__ . '/bootstrap.php';

$value = '';
$transactions = 0;
$read = static function () use (&$value): string {
    return $value;
};
$write = static function (string $updated) use (&$value): void {
    $value = $updated;
};
$transaction = static function (callable $callback) use (&$transactions): mixed {
    $transactions++;

    return $callback();
};
$manager = new AuthorizedKeysManager($read, $write, $transaction);
$sessionId = '0123456789abcdef0123456789abcdef';
$managedLine = RemoteSupportConfig::WARPGATE_PUBLIC_KEY
    . ' # mikopbx-remote-support:'
    . $sessionId;

$manager->install($sessionId);
contractAssertSame($managedLine, $value, 'install appends to an empty setting');
$manager->removeManagedKeys();
contractAssertSame('', $value, 'cleanup restores an empty setting');

$adminKey = 'ssh-ed25519 AAAAC3NzaAdmin admin@example';
$value = $adminKey;
$manager->install($sessionId);
contractAssertSame(
    $adminKey . "\n" . $managedLine,
    $value,
    'install preserves a setting without final newline',
);
$manager->removeManagedKeys();
contractAssertSame($adminKey, $value, 'cleanup preserves no-final-newline style');

$value = $adminKey . "\n";
$manager->install($sessionId);
contractAssertSame(
    $adminKey . "\n" . $managedLine . "\n",
    $value,
    'install preserves a final newline',
);
$manager->removeManagedKeys();
contractAssertSame($adminKey . "\n", $value, 'cleanup preserves the final newline');

$stale = RemoteSupportConfig::WARPGATE_PUBLIC_KEY
    . ' # mikopbx-remote-support:aaaaaaaaaaaaaaaa';
$value = $adminKey . "\n" . $stale;
$manager->install($sessionId);
contractAssertSame(
    $adminKey . "\n" . $managedLine,
    $value,
    'install replaces stale module-marked lines',
);

$newAdminKey = 'ssh-rsa AAAAB3NzaNew administrator-added-during-session';
$value .= "\n" . $newAdminKey;
$manager->removeManagedKeys();
contractAssertSame(
    $adminKey . "\n" . $newAdminKey,
    $value,
    'cleanup preserves administrator keys added after install',
);
$manager->removeManagedKeys();
contractAssertSame(
    $adminKey . "\n" . $newAdminKey,
    $value,
    'cleanup is idempotent',
);

$similarComment = '# prefix mikopbx-remote-support:' . $sessionId . ' suffix';
$similarKey = 'ssh-ed25519 AAAAC3NzaSimilar comment-mikopbx-remote-support:' . $sessionId;
$value = $similarComment . "\n" . $similarKey;
$manager->removeManagedKeys();
contractAssertSame(
    $similarComment . "\n" . $similarKey,
    $value,
    'cleanup preserves merely similar comments and keys',
);

foreach (["bad\nid", "bad\rid", 'short'] as $invalidSessionId) {
    $rejected = false;
    try {
        $manager->install($invalidSessionId);
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    contractAssert($rejected, 'invalid session IDs must be rejected');
}

$source = file_get_contents(dirname(__DIR__) . '/Lib/AuthorizedKeysManager.php');
contractAssert(is_string($source), 'manager source is readable');
contractAssert(
    !str_contains($source, '/root/.ssh/authorized_keys'),
    'manager never references the authorized-keys file',
);
contractAssert(
    !str_contains($source, 'file_put_contents'),
    'manager persists only through PbxSettings',
);
contractAssert($transactions >= 10, 'every update is transactional');

echo "Authorized keys contract: OK\n";
