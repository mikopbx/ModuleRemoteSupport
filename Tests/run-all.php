<?php

declare(strict_types=1);

$tests = [
    __DIR__ . '/check-module-structure.php',
    __DIR__ . '/check-session-state-contract.php',
    __DIR__ . '/check-lobby-protocol-contract.php',
    __DIR__ . '/check-ssh-process-contract.php',
    __DIR__ . '/check-authorized-keys-contract.php',
    __DIR__ . '/check-worker-lifecycle-contract.php',
    __DIR__ . '/check-api-contract.php',
    __DIR__ . '/check-ui-contract.php',
    __DIR__ . '/check-translations-contract.php',
    __DIR__ . '/check-module-lifecycle-contract.php',
    __DIR__ . '/check-release-contract.php',
    __DIR__ . '/check-readme-contract.php',
];

foreach ($tests as $test) {
    require $test;
}

echo sprintf("All contract tests passed (%d files).\n", count($tests));
