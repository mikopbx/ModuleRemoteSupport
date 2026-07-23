<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib\RestAPI\Session;

use MikoPBX\PBXCoreREST\Attributes\ApiDataSchema;
use MikoPBX\PBXCoreREST\Attributes\ApiOperation;
use MikoPBX\PBXCoreREST\Attributes\ApiResource;
use MikoPBX\PBXCoreREST\Attributes\ApiResponse;
use MikoPBX\PBXCoreREST\Attributes\HttpMapping;
use MikoPBX\PBXCoreREST\Attributes\ResourceSecurity;
use MikoPBX\PBXCoreREST\Attributes\SecurityType;
use MikoPBX\PBXCoreREST\Controllers\BaseRestController;

#[ApiResource(
    path: '/pbxcore/api/v3/module-remote-support/session',
    tags: ['ModuleRemoteSupport'],
    description: 'rest_session_ResourceDescription',
    processor: Processor::class,
)]
#[HttpMapping(
    mapping: [
        'GET' => ['getStatus'],
        'POST' => ['start', 'stop'],
    ],
    resourceLevelMethods: [],
    collectionLevelMethods: ['getStatus'],
    customMethods: ['start', 'stop'],
)]
#[ResourceSecurity(
    'module-remote-support-session',
    requirements: [SecurityType::LOCALHOST, SecurityType::BEARER_TOKEN],
)]
class Controller extends BaseRestController
{
    protected string $processorClass = Processor::class;

    #[ApiDataSchema(schemaClass: DataStructure::class, type: 'detail')]
    #[ApiOperation(
        summary: 'rest_session_GetStatus',
        description: 'rest_session_GetStatusDesc',
        operationId: 'getRemoteSupportSession',
    )]
    #[ApiResponse(200, 'rest_response_200_get')]
    #[ApiResponse(401, 'rest_response_401_unauthorized')]
    #[ApiResponse(500, 'rest_response_500_error')]
    public function getStatus(): void
    {
    }

    #[ApiDataSchema(schemaClass: DataStructure::class, type: 'detail')]
    #[ApiOperation(
        summary: 'rest_session_Start',
        description: 'rest_session_StartDesc',
        operationId: 'startRemoteSupportSession',
    )]
    #[ApiResponse(200, 'rest_response_200_get')]
    #[ApiResponse(401, 'rest_response_401_unauthorized')]
    #[ApiResponse(500, 'rest_response_500_error')]
    public function start(): void
    {
    }

    #[ApiDataSchema(schemaClass: DataStructure::class, type: 'detail')]
    #[ApiOperation(
        summary: 'rest_session_Stop',
        description: 'rest_session_StopDesc',
        operationId: 'stopRemoteSupportSession',
    )]
    #[ApiResponse(200, 'rest_response_200_get')]
    #[ApiResponse(401, 'rest_response_401_unauthorized')]
    #[ApiResponse(500, 'rest_response_500_error')]
    public function stop(): void
    {
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function getActionMapping(): array
    {
        return [
            'GET' => [
                'collection' => 'getStatus',
            ],
        ];
    }
}
