<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$messagesDirectory = $root . '/Messages';
$expectedLocales = [
    'az',
    'cs',
    'da',
    'de',
    'el',
    'en',
    'es',
    'fi',
    'fr',
    'hr',
    'hu',
    'it',
    'ja',
    'ka',
    'ko',
    'mn',
    'nl',
    'pl',
    'pt',
    'pt_BR',
    'ro',
    'ru',
    'sv',
    'th',
    'tr',
    'uk',
    'vi',
    'zh_Hans',
    'zh_TW',
];
$expectedFiles = array_map(
    static fn(string $locale): string => $locale . '.php',
    $expectedLocales,
);
sort($expectedFiles);

$actualFiles = array_map(
    'basename',
    glob($messagesDirectory . '/*.php') ?: [],
);
sort($actualFiles);
contractAssertSame($expectedFiles, $actualFiles, 'exactly 29 translation catalogs exist');

/**
 * @return array<string, string>
 */
$loadCatalog = static function (string $path): array {
    $catalog = require $path;
    contractAssert(is_array($catalog), basename($path) . ' returns an array');

    foreach ($catalog as $key => $value) {
        contractAssert(is_string($key), basename($path) . ' contains only string keys');
        contractAssert(is_string($value), basename($path) . ' contains only string values');
    }

    /** @var array<string, string> $catalog */
    return $catalog;
};

$russian = $loadCatalog($messagesDirectory . '/ru.php');
$russianKeys = array_keys($russian);
sort($russianKeys);
contractAssert($russianKeys !== [], 'Russian source catalog is not empty');

$referencedKeys = [];
$sourcePaths = [
    $root . '/App/Views/ModuleRemoteSupport/index.volt',
    $root . '/public/assets/js/src/module-remote-support.js',
    $root . '/Lib/RestAPI/Session/Controller.php',
    $root . '/Lib/RestAPI/Session/DataStructure.php',
    $root . '/Lib/RestAPI/Session/Processor.php',
    $root . '/Lib/RestAPI/Session/Actions/GetStatusAction.php',
];

foreach ($sourcePaths as $sourcePath) {
    $contents = file_get_contents($sourcePath);
    contractAssert(is_string($contents), basename($sourcePath) . ' is readable');
    preg_match_all(
        '/(?:module_remote_support|rest_session|rest_schema_session|rest_error)_[A-Za-z0-9_]+/',
        $contents,
        $matches,
    );
    $referencedKeys = array_merge($referencedKeys, $matches[0]);
}

$referencedKeys = array_merge(
    $referencedKeys,
    [
        'AdditionalMenuItemModuleRemoteSupport',
        'module_remote_support_ErrorAllocation',
        'module_remote_support_ErrorCleanup',
        'module_remote_support_ErrorDisconnected',
        'module_remote_support_ErrorInternal',
        'module_remote_support_ErrorKeyInstall',
        'module_remote_support_ErrorNetwork',
        'module_remote_support_ErrorPreflight',
        'module_remote_support_ErrorRuntime',
        'module_remote_support_ErrorTunnelStart',
    ],
);
$referencedKeys = array_values(array_unique($referencedKeys));
sort($referencedKeys);

$missingReferencedKeys = array_values(array_diff($referencedKeys, $russianKeys));
contractAssertSame([], $missingReferencedKeys, 'Russian catalog covers every referenced key');

$identicalToRussianAllowlist = [
    'module_remote_support_Title',
];

foreach ($expectedLocales as $locale) {
    $catalogPath = $messagesDirectory . '/' . $locale . '.php';
    $catalogSource = file_get_contents($catalogPath);
    contractAssert(is_string($catalogSource), $locale . ' catalog source is readable');
    contractAssert(
        preg_match(
            '/\b(?:require|require_once|include|include_once|array_keys|array_combine)\b/',
            $catalogSource,
        ) !== 1,
        $locale . ' catalog performs no runtime composition',
    );
    $returnOffset = strpos($catalogSource, 'return');
    contractAssert($returnOffset !== false, $locale . ' catalog has a return statement');
    $catalogPreamble = substr($catalogSource, 0, $returnOffset);
    contractAssert(
        !str_contains($catalogPreamble, '$'),
        $locale . ' catalog defines no variables before return',
    );
    contractAssert(
        preg_match('/\breturn\s*\[/', $catalogSource) === 1,
        $locale . ' catalog directly returns a literal array',
    );

    $catalog = $loadCatalog($catalogPath);
    $keys = array_keys($catalog);
    sort($keys);
    contractAssertSame($russianKeys, $keys, $locale . ' has the exact Russian key set');

    foreach ($russian as $key => $russianValue) {
        $value = trim($catalog[$key]);
        contractAssert($value !== '', $locale . ':' . $key . ' is not empty');

        preg_match_all('/%[A-Za-z0-9_]+%/', $russianValue, $russianPlaceholders);
        preg_match_all('/%[A-Za-z0-9_]+%/', $value, $localePlaceholders);
        sort($russianPlaceholders[0]);
        sort($localePlaceholders[0]);
        contractAssertSame(
            $russianPlaceholders[0],
            $localePlaceholders[0],
            $locale . ':' . $key . ' preserves placeholders',
        );

        if ($locale !== 'ru' && !in_array($key, $identicalToRussianAllowlist, true)) {
            contractAssert(
                $value !== trim($russianValue),
                $locale . ':' . $key . ' is translated from Russian',
            );
        }
    }
}

echo "Translations contract: OK\n";
