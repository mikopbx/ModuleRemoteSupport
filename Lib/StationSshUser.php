<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use MikoPBX\Common\Models\PbxSettings;
use RuntimeException;
use Throwable;

/**
 * The account the engineer lands on when the support gateway opens the slot.
 *
 * Most stations keep `root`, but the login is a station setting, so the lobby
 * cannot assume it: the value travels to the server in the allocation request
 * and is written into the slot target for the duration of the session. It is
 * therefore validated as a strict POSIX login here — never quoted or escaped
 * downstream.
 */
final readonly class StationSshUser
{
    private const string PATTERN = '/^[a-z_][a-z0-9_-]{0,31}$/D';

    public const string DEFAULT_LOGIN = 'root';

    public function __construct(public string $login)
    {
        if (preg_match(self::PATTERN, $login) !== 1) {
            throw new RuntimeException('Invalid station SSH login');
        }
    }

    /**
     * Reads the station SSH login. An unreadable or unusable setting degrades to
     * the MikoPBX default account instead of failing the support session.
     */
    public static function fromPbxConfiguration(): self
    {
        try {
            return new self((string)PbxSettings::getValueByKey(PbxSettings::SSH_LOGIN));
        } catch (Throwable) {
            return new self(self::DEFAULT_LOGIN);
        }
    }

    /**
     * The lobby request line sent as the SSH remote command. The server answers
     * v1 when it is absent, so the web channel depends on this string.
     */
    public function allocationRequest(): string
    {
        return sprintf(
            'protocol=%d station_user=%s',
            RemoteSupportConfig::LOBBY_PROTOCOL_VERSION,
            $this->login,
        );
    }
}
