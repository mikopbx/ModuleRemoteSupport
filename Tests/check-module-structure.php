<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$manifestPath = $root . '/module.json';

contractAssert(is_file($manifestPath), 'module.json must exist');

$manifestContents = file_get_contents($manifestPath);
contractAssert($manifestContents !== false, 'module.json must be readable');

try {
    $manifest = json_decode($manifestContents, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    throw new RuntimeException('module.json must contain valid JSON', 0, $exception);
}

contractAssert(is_array($manifest), 'module.json must contain an object');
contractAssertSame(
    'ModuleRemoteSupport',
    $manifest['moduleUniqueID'] ?? null,
    'moduleUniqueID must identify ModuleRemoteSupport',
);
contractAssertSame(
    '2025.1.1',
    $manifest['min_pbx_version'] ?? null,
    'min_pbx_version must be 2025.1.1',
);
contractAssert(is_file($root . '/composer.json'), 'composer.json must exist');
contractAssert(is_file($root . '/App/Module.php'), 'App/Module.php must exist');
contractAssert(
    is_file($root . '/Lib/RemoteSupportConfig.php'),
    'Lib/RemoteSupportConfig.php must exist',
);

echo "Module structure contract: OK\n";
