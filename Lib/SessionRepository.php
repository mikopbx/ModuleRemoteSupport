<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use Closure;
use Modules\ModuleRemoteSupport\Models\RemoteSupportSession;
use RuntimeException;
use Throwable;

final class SessionRepository
{
    private const int ROW_ID = 1;
    private const string SESSION_ID_PATTERN = '/\A[a-zA-Z0-9-]{16,64}\z/D';
    private const string ERROR_CODE_PATTERN = '/\A[a-z][a-z0-9_]{0,63}\z/D';

    /** @var Closure(): RemoteSupportSession */
    private readonly Closure $load;

    /** @var Closure(RemoteSupportSession): void */
    private readonly Closure $save;

    /** @var Closure(Closure(): RemoteSupportSession): RemoteSupportSession */
    private readonly Closure $transaction;

    /** @var Closure(): int */
    private readonly Closure $clock;

    /**
     * @param null|Closure(): RemoteSupportSession $load
     * @param null|Closure(RemoteSupportSession): void $save
     * @param null|Closure(Closure(): RemoteSupportSession): RemoteSupportSession $transaction
     * @param null|Closure(): int $clock
     */
    public function __construct(
        ?Closure $load = null,
        ?Closure $save = null,
        ?Closure $transaction = null,
        ?Closure $clock = null,
    ) {
        $this->load = $load ?? static function (): RemoteSupportSession {
            $session = RemoteSupportSession::findFirstById(self::ROW_ID);

            return $session instanceof RemoteSupportSession
                ? $session
                : self::newSession();
        };
        $this->save = $save ?? static function (RemoteSupportSession $session): void {
            if (!$session->save()) {
                throw new RuntimeException(
                    implode(', ', $session->getMessages()),
                );
            }
        };
        $this->transaction = $transaction ?? static function (
            Closure $callback,
        ): RemoteSupportSession {
            $model = self::newSession();
            $connection = $model->getWriteConnection();
            $connection->begin();

            try {
                $result = $callback();
                if (!$result instanceof RemoteSupportSession) {
                    throw new RuntimeException(
                        'Session transaction returned an invalid result',
                    );
                }
                $connection->commit();

                return $result;
            } catch (Throwable $exception) {
                $connection->rollback();
                throw $exception;
            }
        };
        $this->clock = $clock ?? time(...);
    }

    public function get(): RemoteSupportSession
    {
        $session = ($this->load)();
        $this->assertSingleRow($session);

        return $session;
    }

    public function beginStart(string $sessionId): RemoteSupportSession
    {
        if (preg_match(self::SESSION_ID_PATTERN, $sessionId) !== 1) {
            throw new SessionTransitionException('Invalid session identifier');
        }

        return $this->mutate(
            static function (RemoteSupportSession $session) use ($sessionId): void {
                $status = SessionStatus::from((string)$session->status);
                if ($status === SessionStatus::STARTING || $status === SessionStatus::ACTIVE) {
                    return;
                }
                if ($status !== SessionStatus::OFF && $status !== SessionStatus::ERROR) {
                    throw self::invalidTransition($status, 'start');
                }

                self::clearConnectionFields($session);
                $session->status = SessionStatus::STARTING->value;
                $session->session_id = $sessionId;
            },
        );
    }

    public function markActive(
        string $code,
        int $slot,
        int $tunnelPort,
        int $startedAt,
        int $expiresAt,
    ): RemoteSupportSession {
        if (
            preg_match('/\A[A-Z0-9]{3}-[A-Z0-9]{3}\z/D', $code) !== 1
            || $slot < 0
            || $slot > 999
            || $tunnelPort < 1
            || $tunnelPort > 65_535
            || $startedAt <= 0
            || $expiresAt <= $startedAt
        ) {
            throw new SessionTransitionException('Invalid active-session data');
        }

        return $this->mutate(
            static function (RemoteSupportSession $session) use (
                $code,
                $slot,
                $tunnelPort,
                $startedAt,
                $expiresAt,
            ): void {
                $status = SessionStatus::from((string)$session->status);
                if ($status !== SessionStatus::STARTING) {
                    throw self::invalidTransition($status, 'activate');
                }

                $session->status = SessionStatus::ACTIVE->value;
                $session->code = $code;
                $session->slot = (string)$slot;
                $session->tunnel_port = (string)$tunnelPort;
                $session->started_at = (string)$startedAt;
                $session->expires_at = (string)$expiresAt;
                $session->error_code = '';
            },
        );
    }

    public function beginStop(): RemoteSupportSession
    {
        return $this->mutate(
            static function (RemoteSupportSession $session): void {
                $status = SessionStatus::from((string)$session->status);
                if ($status === SessionStatus::STOPPING || $status === SessionStatus::OFF) {
                    return;
                }
                $session->status = SessionStatus::STOPPING->value;
            },
        );
    }

    public function markError(string $safeErrorCode): RemoteSupportSession
    {
        if (preg_match(self::ERROR_CODE_PATTERN, $safeErrorCode) !== 1) {
            throw new SessionTransitionException('Invalid safe error code');
        }

        return $this->mutate(
            static function (RemoteSupportSession $session) use ($safeErrorCode): void {
                $session->status = SessionStatus::ERROR->value;
                $session->error_code = $safeErrorCode;
                $session->code = '';
                $session->slot = '';
                $session->tunnel_port = '';
                $session->started_at = '';
                $session->expires_at = '';
            },
        );
    }

    public function resetToOff(): RemoteSupportSession
    {
        return $this->mutate(
            static function (RemoteSupportSession $session): void {
                self::clearConnectionFields($session);
                $session->status = SessionStatus::OFF->value;
                $session->session_id = '';
            },
        );
    }

    /**
     * @param callable(RemoteSupportSession): void $change
     */
    private function mutate(callable $change): RemoteSupportSession
    {
        return ($this->transaction)(
            function () use ($change): RemoteSupportSession {
                $session = ($this->load)();
                $this->assertSingleRow($session);
                $change($session);
                $session->updated_at = (string)($this->clock)();
                ($this->save)($session);

                return $session;
            },
        );
    }

    private function assertSingleRow(RemoteSupportSession $session): void
    {
        if ($session->id !== self::ROW_ID) {
            throw new RuntimeException('Remote support session row must have ID 1');
        }
    }

    private static function newSession(): RemoteSupportSession
    {
        $session = new RemoteSupportSession();
        $session->id = self::ROW_ID;
        $session->status = SessionStatus::OFF->value;

        return $session;
    }

    private static function clearConnectionFields(RemoteSupportSession $session): void
    {
        $session->code = '';
        $session->slot = '';
        $session->tunnel_port = '';
        $session->started_at = '';
        $session->expires_at = '';
        $session->error_code = '';
    }

    private static function invalidTransition(
        SessionStatus $status,
        string $operation,
    ): SessionTransitionException {
        return new SessionTransitionException(
            sprintf('Cannot %s a session in state %s', $operation, $status->value),
        );
    }
}
