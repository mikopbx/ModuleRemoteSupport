<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib\RestAPI\Session\Actions;

use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleRemoteSupport\Lib\RemoteSupportRuntimeInterface;
use Modules\ModuleRemoteSupport\Lib\RuntimeDirectory;
use Modules\ModuleRemoteSupport\Lib\SessionRepository;
use Modules\ModuleRemoteSupport\Lib\SessionStatus;
use Modules\ModuleRemoteSupport\Models\RemoteSupportSession;
use Throwable;

final class GetStatusAction
{
    /**
     * @param array<string, mixed> $data
     */
    public static function main(array $data): PBXApiResult
    {
        try {
            $session = (new SessionRepository())->get();

            return self::fromSession($session, self::webPassword($session, new RuntimeDirectory()));
        } catch (Throwable) {
            return self::safeError();
        }
    }

    public static function fromSession(RemoteSupportSession $session, string $webPassword = ''): PBXApiResult
    {
        $result = new PBXApiResult();
        $result->success = true;
        $result->data = self::sessionData($session, $webPassword);

        return $result;
    }

    /**
     * @return array{
     *     state: string,
     *     code: string,
     *     startedAt: int,
     *     expiresAt: int,
     *     errorCode: string,
     *     webLogin: string,
     *     webPassword: string
     * }
     */
    public static function sessionData(RemoteSupportSession $session, string $webPassword = ''): array
    {
        $isActive = (string)$session->status === SessionStatus::ACTIVE->value;

        return [
            'state' => (string)$session->status,
            'code' => (string)$session->code,
            'startedAt' => (int)$session->started_at,
            'expiresAt' => (int)$session->expires_at,
            'errorCode' => (string)$session->error_code,
            'webLogin' => $isActive ? (string)$session->web_login : '',
            'webPassword' => $isActive ? $webPassword : '',
        ];
    }

    /**
     * Reads the plaintext web password from the private runtime, and only for
     * the active session whose stored login matches the runtime credential.
     */
    private static function webPassword(
        RemoteSupportSession $session,
        RemoteSupportRuntimeInterface $runtime,
    ): string {
        if ((string)$session->status !== SessionStatus::ACTIVE->value) {
            return '';
        }

        $credential = $runtime->readWebCredential();
        if ($credential === null || $credential['login'] !== (string)$session->web_login) {
            return '';
        }

        return $credential['password'];
    }

    public static function safeError(): PBXApiResult
    {
        $result = new PBXApiResult();
        $result->messages['error'] = ['rest_error_internal'];

        return $result;
    }
}
