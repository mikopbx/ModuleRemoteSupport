<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use Closure;
use InvalidArgumentException;
use MikoPBX\Common\Models\PbxSettings;
use MikoPBX\Common\Providers\MainDatabaseProvider;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Di\Di;
use RuntimeException;
use Throwable;

final class AuthorizedKeysManager implements AuthorizedKeysManagerInterface
{
    private const string SESSION_ID_PATTERN = '/\A[a-zA-Z0-9-]{16,64}\z/D';
    private const string MANAGED_LINE_PATTERN =
        '/\s+# mikopbx-remote-support:[a-zA-Z0-9-]{16,64}\z/D';

    /** @var Closure(): string */
    private readonly Closure $read;

    /** @var Closure(string): void */
    private readonly Closure $write;

    /** @var Closure(callable(): mixed): mixed */
    private readonly Closure $transaction;

    /**
     * @param null|Closure(): string $read
     * @param null|Closure(string): void $write
     * @param null|Closure(callable(): mixed): mixed $transaction
     */
    public function __construct(
        ?Closure $read = null,
        ?Closure $write = null,
        ?Closure $transaction = null,
    ) {
        $this->read = $read ?? static fn(): string => PbxSettings::getValueByKey(
            PbxSettings::SSH_AUTHORIZED_KEYS,
            false,
        );
        $this->write = $write ?? static function (string $value): void {
            $messages = [];
            if (!PbxSettings::setValueByKey(PbxSettings::SSH_AUTHORIZED_KEYS, $value, $messages)) {
                throw new RuntimeException('Unable to update MikoPBX SSH access setting');
            }
        };
        $this->transaction = $transaction ?? static function (callable $callback): mixed {
            $container = Di::getDefault();
            if ($container === null) {
                throw new RuntimeException('MikoPBX dependency injection is unavailable');
            }

            $database = $container->getShared(MainDatabaseProvider::SERVICE_NAME);
            if (!$database instanceof AdapterInterface) {
                throw new RuntimeException('MikoPBX main database is unavailable');
            }

            $database->begin();
            try {
                $result = $callback();
                $database->commit();

                return $result;
            } catch (Throwable $exception) {
                $database->rollback();
                throw $exception;
            }
        };
    }

    public function install(string $sessionId): void
    {
        if (preg_match(self::SESSION_ID_PATTERN, $sessionId) !== 1) {
            throw new InvalidArgumentException('Invalid remote-support session identifier');
        }

        ($this->transaction)(
            function () use ($sessionId): void {
                [$lines, $hadFinalNewline] = $this->readUnmanagedLines();
                $lines[] = RemoteSupportConfig::WARPGATE_PUBLIC_KEY
                    . ' # mikopbx-remote-support:'
                    . $sessionId;
                ($this->write)($this->joinLines($lines, $hadFinalNewline));
            },
        );
    }

    public function removeManagedKeys(): void
    {
        ($this->transaction)(
            function (): void {
                $current = ($this->read)();
                [$lines, $hadFinalNewline] = $this->filterManagedLines($current);
                $updated = $this->joinLines($lines, $hadFinalNewline);
                if ($updated !== $current) {
                    ($this->write)($updated);
                }
            },
        );
    }

    /**
     * @return array{0: list<string>, 1: bool}
     */
    private function readUnmanagedLines(): array
    {
        return $this->filterManagedLines(($this->read)());
    }

    /**
     * @return array{0: list<string>, 1: bool}
     */
    private function filterManagedLines(string $value): array
    {
        $hadFinalNewline = str_ends_with($value, "\n");
        $content = $hadFinalNewline ? substr($value, 0, -1) : $value;
        $lines = $content === '' ? [] : explode("\n", $content);
        $unmanaged = [];

        foreach ($lines as $line) {
            if (preg_match(self::MANAGED_LINE_PATTERN, $line) === 1) {
                continue;
            }
            $unmanaged[] = $line;
        }

        return [$unmanaged, $hadFinalNewline];
    }

    /**
     * @param list<string> $lines
     */
    private function joinLines(array $lines, bool $hadFinalNewline): string
    {
        if ($lines === []) {
            return '';
        }

        $value = implode("\n", $lines);

        return $hadFinalNewline ? $value . "\n" : $value;
    }
}
