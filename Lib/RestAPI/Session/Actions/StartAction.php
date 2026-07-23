<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib\RestAPI\Session\Actions;

use Closure;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleRemoteSupport\Lib\RemoteSupportMain;
use Modules\ModuleRemoteSupport\Models\RemoteSupportSession;
use Throwable;

final class StartAction
{
    /**
     * @param array<string, mixed> $data
     */
    public static function main(array $data): PBXApiResult
    {
        $main = new RemoteSupportMain();

        return self::execute($main->requestStart(...));
    }

    /**
     * @param Closure(): RemoteSupportSession $requestStart
     */
    public static function execute(Closure $requestStart): PBXApiResult
    {
        try {
            $result = new PBXApiResult();
            $result->data = GetStatusAction::sessionData($requestStart());
            $result->success = true;

            return $result;
        } catch (Throwable) {
            return GetStatusAction::safeError();
        }
    }
}
