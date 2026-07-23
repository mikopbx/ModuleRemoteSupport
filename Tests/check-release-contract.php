<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$workflowPath = $root . '/.github/workflows/build.yml';
contractAssert(is_file($workflowPath), 'build and publish workflow exists');

$workflow = file_get_contents($workflowPath);
contractAssert(is_string($workflow), 'build and publish workflow is readable');
foreach (
    [
        'name: Build and Publish',
        'push:',
        '- master',
        '- develop',
        'workflow_dispatch:',
        'uses: mikopbx/.github-workflows/.github/workflows/extension-publish.yml@master',
        'initial_version: "1.0"',
        'secrets: inherit',
    ] as $requiredFragment
) {
    contractAssert(
        str_contains($workflow, $requiredFragment),
        'workflow contains ' . $requiredFragment,
    );
}
contractAssert(
    !str_contains($workflow, 'custom_build_steps:'),
    'workflow uses the shared build without unsupported custom steps',
);

$manifest = json_decode(
    (string)file_get_contents($root . '/module.json'),
    true,
    32,
    JSON_THROW_ON_ERROR,
);
contractAssert(is_array($manifest), 'module manifest is an object');
contractAssertSame(
    [
        'publish_release' => true,
        'changelog_enabled' => true,
        'create_github_release' => true,
    ],
    $manifest['release_settings'] ?? null,
    'release settings enable publishing, changelog, and GitHub releases',
);

$composer = json_decode(
    (string)file_get_contents($root . '/composer.json'),
    true,
    32,
    JSON_THROW_ON_ERROR,
);
contractAssert(is_array($composer), 'Composer metadata is an object');
contractAssertSame('~8.4', $composer['require']['php'] ?? null, 'Composer requires PHP 8.4');
contractAssertSame(
    '^5.0',
    $composer['require']['ext-phalcon'] ?? null,
    'Composer requires Phalcon 5',
);

echo "Release automation contract: OK\n";
