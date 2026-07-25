<?php

declare(strict_types=1);

use Modules\ModuleRemoteSupport\Lib\RuntimeDirectory;

require_once __DIR__ . '/bootstrap.php';

$baseDirectory = MikoPBX\Core\System\Directories::getDir('core.tempDir')
    . '/module-remote-support';
$credentialPath = $baseDirectory . '/web-credential.json';
if (is_file($credentialPath)) {
    unlink($credentialPath);
}

$runtime = new RuntimeDirectory();
contractAssertSame(
    null,
    $runtime->readWebCredential(),
    'missing credential file reads as null',
);

$runtime->writeWebCredential('miko-support-0a1b2c3d4e5f6a7b', 'S3cr3t-Example');
contractAssert(is_file($credentialPath), 'credential file lives at the fixed private path');
contractAssertSame(
    0o640,
    fileperms($credentialPath) & 0o777,
    'credential file is owner-writable and group-readable so php-fpm (www) can read it',
);
contractAssert(
    (fileperms($credentialPath) & 0o007) === 0,
    'credential file is never world-readable',
);
contractAssertSame(
    0o710,
    fileperms($baseDirectory) & 0o777,
    'base directory lets the web group traverse to the credential but not list it',
);
contractAssert(
    (fileperms($baseDirectory) & 0o007) === 0,
    'base directory is never world-accessible',
);
contractAssertSame(
    ['login' => 'miko-support-0a1b2c3d4e5f6a7b', 'password' => 'S3cr3t-Example'],
    $runtime->readWebCredential(),
    'credential file round-trips login and password',
);

$reader = new RuntimeDirectory();
contractAssertSame(
    ['login' => 'miko-support-0a1b2c3d4e5f6a7b', 'password' => 'S3cr3t-Example'],
    $reader->readWebCredential(),
    'another process instance can read the credential without session state',
);

$runtime->cleanup();
contractAssert(!is_file($credentialPath), 'cleanup removes the credential file');
contractAssertSame(null, $runtime->readWebCredential(), 'cleanup leaves nothing to read');

$runtime->writeWebCredential('miko-support-0a1b2c3d4e5f6a7b', 'S3cr3t-Example');
$runtime->cleanupStale();
contractAssert(!is_file($credentialPath), 'stale cleanup removes the credential file');

file_put_contents($credentialPath, 'not-json');
chmod($credentialPath, 0o600);
contractAssertSame(
    null,
    (new RuntimeDirectory())->readWebCredential(),
    'corrupted credential file reads as null',
);
unlink($credentialPath);

echo "Web credential contract: OK\n";
