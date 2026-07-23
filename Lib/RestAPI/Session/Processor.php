<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib\RestAPI\Session;

use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleRemoteSupport\Lib\RestAPI\Session\Actions\GetStatusAction;
use Modules\ModuleRemoteSupport\Lib\RestAPI\Session\Actions\StartAction;
use Modules\ModuleRemoteSupport\Lib\RestAPI\Session\Actions\StopAction;
use Phalcon\Di\Injectable;

class Processor extends Injectable
{
    /**
     * @param array<string, mixed> $request
     */
    public static function callBack(array $request): PBXApiResult
    {
        $action = is_string($request['action'] ?? null) ? $request['action'] : '';
        $data = [];
        if (is_array($request['data'] ?? null)) {
            foreach ($request['data'] as $key => $value) {
                if (is_string($key)) {
                    $data[$key] = $value;
                }
            }
        }

        $result = match ($action) {
            'getStatus' => GetStatusAction::main($data),
            'start' => StartAction::main($data),
            'stop' => StopAction::main($data),
            default => self::unknownAction(),
        };
        $result->processor = __METHOD__;
        $result->function = $action;

        return $result;
    }

    private static function unknownAction(): PBXApiResult
    {
        $result = new PBXApiResult();
        $result->messages['error'] = ['rest_error_unknown_action'];

        return $result;
    }
}
