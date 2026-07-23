<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib\RestAPI\Session\Actions;

use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleRemoteSupport\Lib\SessionRepository;
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
            return self::fromSession((new SessionRepository())->get());
        } catch (Throwable) {
            return self::safeError();
        }
    }

    public static function fromSession(RemoteSupportSession $session): PBXApiResult
    {
        $result = new PBXApiResult();
        $result->success = true;
        $result->data = self::sessionData($session);

        return $result;
    }

    /**
     * @return array{
     *     state: string,
     *     code: string,
     *     startedAt: int,
     *     expiresAt: int,
     *     errorCode: string
     * }
     */
    public static function sessionData(RemoteSupportSession $session): array
    {
        return [
            'state' => (string)$session->status,
            'code' => (string)$session->code,
            'startedAt' => (int)$session->started_at,
            'expiresAt' => (int)$session->expires_at,
            'errorCode' => (string)$session->error_code,
        ];
    }

    public static function safeError(): PBXApiResult
    {
        $result = new PBXApiResult();
        $result->messages['error'] = ['rest_error_internal'];

        return $result;
    }
}
