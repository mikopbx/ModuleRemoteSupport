<?php

declare(strict_types=1);

$coreAutoloader = '/Users/nb/Developement/mikopbx/Core/vendor/autoload.php';
if (is_file($coreAutoloader)) {
    require_once $coreAutoloader;
}

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'Modules\\ModuleRemoteSupport\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativePath = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = dirname(__DIR__) . '/' . $relativePath . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    },
);

if (!class_exists('MikoPBX\\AdminCabinet\\Controllers\\SessionController', false)) {
    class TestSessionController
    {
        public const string SESSION_ID = 'authAdminCabinet';
        public const string ROLE = 'role';
        public const string HOME_PAGE = 'homePage';
        public const string USER_NAME = 'userName';
    }

    class_alias(
        TestSessionController::class,
        'MikoPBX\\AdminCabinet\\Controllers\\SessionController',
    );
}

if (!class_exists('MikoPBX\\Common\\Providers\\AclProvider', false)) {
    class TestAclProvider
    {
        public const string ROLE_ADMINS = 'admins';
        public const string ROLE_GUESTS = 'guests';
    }

    class_alias(
        TestAclProvider::class,
        'MikoPBX\\Common\\Providers\\AclProvider',
    );
}

if (!class_exists('MikoPBX\\Core\\System\\Directories', false)) {
    class TestDirectories
    {
        public const string CORE_TEMP_DIR = 'core.tempDir';

        public static function getDir(string $dirConstant): string
        {
            $directory = sys_get_temp_dir() . '/module-remote-support-tests';
            if (!is_dir($directory)) {
                mkdir($directory, 0700, true);
            }

            return $directory;
        }
    }

    class_alias(
        TestDirectories::class,
        'MikoPBX\\Core\\System\\Directories',
    );
}

if (!class_exists('MikoPBX\\Modules\\Models\\ModulesModelsBase', false)) {
    class TestModulesModelsBase
    {
        public function initialize(): void
        {
        }

        public function setSource(string $source): void
        {
        }
    }

    class_alias(
        TestModulesModelsBase::class,
        'MikoPBX\\Modules\\Models\\ModulesModelsBase',
    );
}

/**
 * @throws RuntimeException
 */
function contractAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @param mixed $actual
 * @param mixed $expected
 *
 * @throws RuntimeException
 */
function contractAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(
            sprintf(
                '%s (expected %s, got %s)',
                $message,
                var_export($expected, true),
                var_export($actual, true),
            ),
        );
    }
}
